<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Request;

interface PaymentProvider
{
    public function key(): string;

    public function label(): string;

    /** Sandbox-/Live-Hinweis für die Anzeige, z. B. "Sandbox". */
    public function modeLabel(): string;

    public function isEnabled(): bool;

    /** Legt die Zahlung beim Anbieter an und gibt die Weiterleitungs-URL zurück. */
    public function createCheckout(Order $order): string;

    /**
     * Fragt den Zahlungsstatus direkt beim Anbieter ab (nie dem Browser vertrauen).
     *
     * @return string Order::PAID, Order::PENDING oder Order::FAILED
     */
    public function verify(Order $order, ?Request $request = null): string;
}
