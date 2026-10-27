<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\User;
use App\Services\Wall\WallExtender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Shop
{
    public function __construct(private WallExtender $extender) {}

    /** @return array<string, PaymentProvider> nur aktivierte Anbieter */
    public function providers(): array
    {
        $all = [
            new PayPalProvider,
            new StripeProvider,
            new MollieProvider,
            new FakeProvider,
        ];

        $enabled = [];
        foreach ($all as $provider) {
            if ($provider->isEnabled()) {
                $enabled[$provider->key()] = $provider;
            }
        }

        return $enabled;
    }

    public function provider(string $key): PaymentProvider
    {
        return $this->providers()[$key]
            ?? throw new InvalidArgumentException('Unbekannter oder nicht aktivierter Zahlungsanbieter.');
    }

    public function createOrder(User $user, string $product, string $providerKey): Order
    {
        $definition = config('payments.products.'.$product);
        if (! $definition) {
            throw new InvalidArgumentException('Unbekanntes Produkt.');
        }
        $this->provider($providerKey);

        return Order::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'product' => $product,
            'provider' => $providerKey,
            'status' => Order::PENDING,
            'amount_cents' => $definition['price_cents'],
            'currency' => config('payments.currency'),
            'meta' => array_filter(['axis' => $definition['axis'] ?? null, 'amount' => $definition['amount']]),
        ]);
    }

    /** Fragt den Anbieter ab und liefert bei erfolgreicher Zahlung aus. Idempotent. */
    public function refresh(Order $order, ?Request $request = null): Order
    {
        if ($order->status === Order::PENDING) {
            $status = $this->provider($order->provider)->verify($order, $request);
            $this->settle($order, $status);
        }

        return $order->fresh();
    }

    public function settle(Order $order, string $status): void
    {
        if ($status === Order::PAID) {
            $this->fulfill($order);
        } elseif ($status === Order::FAILED && $order->status === Order::PENDING) {
            $order->update(['status' => Order::FAILED]);
        }
    }

    public function fulfill(Order $order): void
    {
        set_time_limit(0);

        DB::transaction(function () use ($order) {
            $locked = Order::query()->lockForUpdate()->find($order->id);
            if (! $locked || $locked->fulfilled_at !== null) {
                return;
            }

            $product = config('payments.products.'.$locked->product);
            if (($product['type'] ?? null) === 'extend') {
                $this->extender->extend($product['axis']);
            } elseif (($product['type'] ?? null) === 'pixels') {
                User::query()->whereKey($locked->user_id)->increment('pixel_credits', (int) $product['amount']);
            }

            $locked->update([
                'status' => Order::PAID,
                'paid_at' => now(),
                'fulfilled_at' => now(),
            ]);
        });
    }

    public function cancel(Order $order): void
    {
        if ($order->status === Order::PENDING) {
            $order->update(['status' => Order::CANCELLED]);
        }
    }
}
