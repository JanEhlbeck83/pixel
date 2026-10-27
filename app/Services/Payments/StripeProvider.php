<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return config('payments.providers.stripe.label');
    }

    public function modeLabel(): string
    {
        return str_starts_with((string) config('payments.providers.stripe.secret_key'), 'sk_live') ? 'Live' : 'Sandbox';
    }

    public function isEnabled(): bool
    {
        return filled(config('payments.providers.stripe.secret_key'));
    }

    public function createCheckout(Order $order): string
    {
        $response = $this->http()->asForm()->post('/v1/checkout/sessions', [
            'mode' => 'payment',
            'locale' => 'de',
            'client_reference_id' => $order->uuid,
            'metadata' => ['order_uuid' => $order->uuid],
            'success_url' => route('shop.return', $order),
            'cancel_url' => route('shop.cancel', $order),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($order->currency),
                    'unit_amount' => $order->amount_cents,
                    'product_data' => ['name' => $order->title()],
                ],
            ]],
        ]);

        if (! $response->successful() || ! $response->json('url')) {
            throw new RuntimeException('Stripe: Zahlung konnte nicht angelegt werden ('.$response->status().').');
        }

        $order->update(['provider_reference' => $response->json('id')]);

        return $response->json('url');
    }

    public function verify(Order $order, ?Request $request = null): string
    {
        if (! $order->provider_reference) {
            return Order::FAILED;
        }

        $response = $this->http()->get('/v1/checkout/sessions/'.$order->provider_reference);
        if (! $response->successful()) {
            return Order::PENDING;
        }

        $session = $response->json();
        if (($session['client_reference_id'] ?? null) !== $order->uuid
            || (int) ($session['amount_total'] ?? -1) !== $order->amount_cents
            || strtoupper((string) ($session['currency'] ?? '')) !== $order->currency) {
            return Order::FAILED;
        }

        if (($session['payment_status'] ?? null) === 'paid') {
            return Order::PAID;
        }

        return ($session['status'] ?? null) === 'expired' ? Order::FAILED : Order::PENDING;
    }

    private function http()
    {
        return Http::baseUrl('https://api.stripe.com')
            ->withToken((string) config('payments.providers.stripe.secret_key'))
            ->acceptJson()
            ->timeout(30);
    }
}
