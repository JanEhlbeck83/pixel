<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Lokale Simulation: kein echter Zahlungsanbieter, nur für Entwicklung/Test.
 * Wird in Produktion nie aktiviert.
 */
class FakeProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return __(config('payments.providers.fake.label'));
    }

    public function modeLabel(): string
    {
        return __('Simulation');
    }

    public function isEnabled(): bool
    {
        return (bool) config('payments.providers.fake.enabled') && ! app()->isProduction();
    }

    public function createCheckout(Order $order): string
    {
        $order->update(['provider_reference' => 'fake_'.$order->uuid]);

        return route('shop.fake', $order);
    }

    public function verify(Order $order, ?Request $request = null): string
    {
        return $order->status === Order::PAID ? Order::PAID : Order::PENDING;
    }
}
