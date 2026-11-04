<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PayPalProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'paypal';
    }

    public function label(): string
    {
        return __(config('payments.providers.paypal.label'));
    }

    public function modeLabel(): string
    {
        return $this->isLive() ? 'Live' : __('Sandbox');
    }

    public function isEnabled(): bool
    {
        return filled(config('payments.providers.paypal.client_id'))
            && filled(config('payments.providers.paypal.client_secret'));
    }

    public function createCheckout(Order $order): string
    {
        $response = $this->http()
            ->withHeaders(['PayPal-Request-Id' => $order->uuid])
            ->post('/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $order->uuid,
                    'custom_id' => $order->uuid,
                    'description' => mb_substr($order->title(), 0, 120),
                    'amount' => [
                        'currency_code' => $order->currency,
                        'value' => number_format($order->amount_cents / 100, 2, '.', ''),
                    ],
                ]],
                'payment_source' => ['paypal' => ['experience_context' => [
                    'brand_name' => config('app.name'),
                    'locale' => ['en' => 'en-US', 'es' => 'es-ES'][app()->getLocale()] ?? 'de-DE',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                    'return_url' => route('shop.return', $order),
                    'cancel_url' => route('shop.cancel', $order),
                ]]],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('PayPal: Zahlung konnte nicht angelegt werden ('.$response->status().').');
        }

        $order->update(['provider_reference' => $response->json('id')]);

        $link = collect($response->json('links', []))
            ->first(fn ($link) => in_array($link['rel'] ?? '', ['payer-action', 'approve'], true));

        if (! $link) {
            throw new RuntimeException('PayPal: Keine Weiterleitungs-URL erhalten.');
        }
        return $link['href'];
    }

    public function verify(Order $order, ?Request $request = null): string
{
    if (! $order->provider_reference) {
        return Order::FAILED;
    }

    $capture = $this->http()
        ->withBody('{}', 'application/json')
        ->withHeaders(['PayPal-Request-Id' => $order->uuid.'-capture'])
        ->post('/v2/checkout/orders/'.$order->provider_reference.'/capture');

    $captureData = $capture->json() ?? [];
    $issues = collect($captureData['details'] ?? []);

    if (! $capture->successful()) {
        $issueCodes = $issues->pluck('issue')->all();

        Log::warning('PayPal Capture nicht erfolgreich', [
            'order' => $order->uuid,
            'http_status' => $capture->status(),
            'name' => $captureData['name'] ?? null,
            'message' => $captureData['message'] ?? null,
            'issues' => $issueCodes,
        ]);

        if (in_array('ORDER_NOT_APPROVED', $issueCodes, true)) {
            return Order::PENDING;
        }
    }

    // Unabhängig vom Capture-Ergebnis den tatsächlichen PayPal-Status abrufen.
    $response = $this->http()->get(
        '/v2/checkout/orders/'.$order->provider_reference
    );

    if (! $response->successful()) {
        Log::warning('PayPal Bestellstatus konnte nicht abgerufen werden', [
            'order' => $order->uuid,
            'http_status' => $response->status(),
        ]);

        return Order::PENDING;
    }

    $data = $response->json() ?? [];
    $units = $data['purchase_units'] ?? [];

if (! is_array($units) || count($units) !== 1) {
    Log::warning('PayPal Anzahl der Purchase Units stimmt nicht', [
        'order' => $order->uuid,
    ]);

    return Order::FAILED;
}

$unit = $units[0];

    // Sicherheitsprüfung: Beide Referenzen müssen unserer Bestellung entsprechen.
    if (($unit['reference_id'] ?? null) !== $order->uuid
        || ($unit['custom_id'] ?? null) !== $order->uuid) {
        Log::warning('PayPal Bestellreferenz stimmt nicht überein', [
            'order' => $order->uuid,
        ]);

        return Order::FAILED;
    }

        $expectedMerchantId = (string) config('payments.providers.paypal.merchant_id');
    $actualMerchantId = $unit['payee']['merchant_id'] ?? null;

    if (
        $expectedMerchantId === ''
        || ! is_string($actualMerchantId)
        || ! hash_equals($expectedMerchantId, $actualMerchantId)
    ) {
        Log::warning('PayPal Händler-ID stimmt nicht überein', [
            'order' => $order->uuid,
        ]);

        return Order::FAILED;
    }

    $payment = $unit['payments']['captures'][0] ?? null;

    if (! $payment) {
        return Order::PENDING;
    }

    $amount = $payment['amount'] ?? [];
    $expected = number_format($order->amount_cents / 100, 2, '.', '');

    if (($data['status'] ?? null) === 'COMPLETED'
        && ($payment['status'] ?? null) === 'COMPLETED'
        && ($amount['value'] ?? null) === $expected
        && ($amount['currency_code'] ?? null) === $order->currency) {
        return Order::PAID;
    }

    return in_array($payment['status'] ?? null, ['PENDING'], true)
        || ($data['status'] ?? null) === 'APPROVED'
        ? Order::PENDING
        : Order::FAILED;
}

   private function isLive(): bool
    {
        return config('payments.providers.paypal.mode') === 'live';
    }

    private function baseUrl(): string
    {
        return $this->isLive() ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withToken($this->accessToken())
            ->acceptJson()
            ->timeout(30);
    }

    private function accessToken(): string
    {
        $cacheKey = 'paypal-token:'.config('payments.providers.paypal.mode').':'.md5((string) config('payments.providers.paypal.client_id'));

        $token = Cache::get($cacheKey);
        if ($token) {
            return $token;
        }

        $response = Http::baseUrl($this->baseUrl())
            ->withBasicAuth(
                (string) config('payments.providers.paypal.client_id'),
                (string) config('payments.providers.paypal.client_secret'),
            )
            ->asForm()
            ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('PayPal: Anmeldung fehlgeschlagen – Client-ID/Secret prüfen.');
        }

        Cache::put($cacheKey, $response->json('access_token'), 300);

        return $response->json('access_token');
    }
}
