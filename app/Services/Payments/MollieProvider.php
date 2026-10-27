<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MollieProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'mollie';
    }

    public function label(): string
    {
        return config('payments.providers.mollie.label');
    }

    public function modeLabel(): string
    {
        return str_starts_with((string) config('payments.providers.mollie.api_key'), 'live_') ? 'Live' : 'Sandbox';
    }

    public function isEnabled(): bool
    {
        return filled(config('payments.providers.mollie.api_key'));
    }

    public function createCheckout(Order $order): string
    {
        $payload = [
            'amount' => [
                'currency' => $order->currency,
                'value' => number_format($order->amount_cents / 100, 2, '.', ''),
            ],
            'description' => $order->title(),
            'redirectUrl' => route('shop.return', $order),
            'cancelUrl' => route('shop.cancel', $order),
            'locale' => 'de_DE',
            'metadata' => ['order_uuid' => $order->uuid],
        ];

        // Mollie lehnt Webhook-URLs ab, die nicht öffentlich erreichbar sind (localhost).
        $webhook = route('webhooks.mollie');
        if (str_starts_with($webhook, 'https://') && ! str_contains($webhook, 'localhost')) {
            $payload['webhookUrl'] = $webhook;
        }

        $response = $this->http()->post('/v2/payments', $payload);
        if (! $response->successful() || ! $response->json('_links.checkout.href')) {
            throw new RuntimeException('Mollie: Zahlung konnte nicht angelegt werden ('.$response->status().').');
        }

        $order->update(['provider_reference' => $response->json('id')]);

        return $response->json('_links.checkout.href');
    }

    public function verify(Order $order, ?Request $request = null): string
    {
        if (! $order->provider_reference) {
            return Order::FAILED;
        }

        $response = $this->http()->get('/v2/payments/'.$order->provider_reference);
        if (! $response->successful()) {
            return Order::PENDING;
        }

        $payment = $response->json();
        $expected = number_format($order->amount_cents / 100, 2, '.', '');

        if (($payment['metadata']['order_uuid'] ?? null) !== $order->uuid
            || ($payment['amount']['value'] ?? null) !== $expected
            || ($payment['amount']['currency'] ?? null) !== $order->currency) {
            return Order::FAILED;
        }

        return match ($payment['status'] ?? null) {
            'paid' => Order::PAID,
            'failed', 'canceled', 'expired' => Order::FAILED,
            default => Order::PENDING,
        };
    }

    private function http()
    {
        return Http::baseUrl('https://api.mollie.com')
            ->withToken((string) config('payments.providers.mollie.api_key'))
            ->acceptJson()
            ->timeout(30);
    }
}
