<?php

/*
|--------------------------------------------------------------------------
| PixRiot – Zahlungen & Shop
|--------------------------------------------------------------------------
|
| ALLE Zugangsdaten und Preise stehen hier bzw. in der .env (siehe
| .env.example, Abschnitt "ZAHLUNGEN"). Im Code selbst steht kein Schlüssel.
|
| Sandbox/Live wird pro Anbieter über "mode" geschaltet:
|   sandbox = Testgeld, es wird nichts abgebucht
|   live    = echtes Geld
|
*/

return [

    'currency' => 'EUR',

    // Preise in Cent. 300 = 3,00 €
    'products' => [
        'extend_x' => [
            'type' => 'extend',
            'axis' => 'x',
            'amount' => 1,
            'price_cents' => (int)             env('PRICE_EXTEND_CENTS', 300),
        ],
        'extend_y' => [
            'type' => 'extend',
            'axis' => 'y',
            'amount' => 1,
            'price_cents' => (int)             env('PRICE_EXTEND_CENTS', 300),
        ],
        // Paket: 5 Pixel auf einmal (günstiger als 5 Einzelkäufe).
        'extend_x_5' => [
            'type' => 'extend',
            'axis' => 'x',
            'amount' => 5,
            'price_cents' => (int) env('PRICE_EXTEND_5_CENTS', 1200),
        ],
        'extend_y_5' => [
            'type' => 'extend',
            'axis' => 'y',
            'amount' => 5,
            'price_cents' => (int) env('PRICE_EXTEND_5_CENTS', 1200),
        ],
        // Der Schlüssel 'pixels_100' ist die ursprüngliche Paket-ID (bestehende Bestellungen), enthält jetzt 250 Pixel.
        'pixels_100' => [
            'type' => 'pixels',
            'amount' => 250,
            'price_cents' => (int) env('PRICE_PIXELS_250_CENTS', 500),
        ],
        'pixels_600' => [
            'type' => 'pixels',
            'amount' => 600,
            'price_cents' => (int) env('PRICE_PIXELS_600_CENTS', 1000),
        ],
        'pixels_1000' => [
            'type' => 'pixels',
            'amount' => 1000,
            'price_cents' => (int) env('PRICE_PIXELS_1000_CENTS', 1500),
        ],
    ],

    // Spielregeln für das Setzen von Pixeln
    'limits' => [
        // Gäste: einmaliger Vorrat pro IP, danach nur mit Konto.
        'guest_pixels' => (int) env('GUEST_PIXELS', 50),
        // Konten: Vorrat (Maximum) und Nachwachsen. Mehr als das Maximum gibt es nur gekauft.
        'user_max_free_pixels' => (int) env('USER_MAX_FREE_PIXELS', 50),
        'user_regen_seconds' => (int) env('USER_REGEN_SECONDS', 30),
        // Höchstbestand an gekauften Extra-Pixeln pro Konto.
        'max_credits' => (int) env('USER_MAX_CREDITS', 10000),
    ],

    'providers' => [

        // PayPal (inkl. Lastschrift/Bankkonto und Kartenzahlung für PayPal-Kunden)
        // Sandbox-Zugang: https://developer.paypal.com/dashboard/applications/sandbox
        'paypal' => [
            'label' => 'PayPal',
            'mode' => env('PAYPAL_MODE', 'sandbox'),
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'merchant_id' => env('PAYPAL_MERCHANT_ID'),
        ],

        // Stripe Checkout: Visa/Mastercard (Kreditkarte), Apple Pay und Google Pay.
        // Apple/Google Pay erscheinen automatisch, wenn sie im Stripe-Dashboard aktiviert sind
        // (Apple Pay zusätzlich: Domain verifizieren) und der Kunde ein passendes Gerät nutzt.
        // Testschlüssel (sk_test_...): https://dashboard.stripe.com/test/apikeys
        'stripe' => [
            'label' => 'Karte / Apple Pay / Google Pay (Stripe)',
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],

        // Lokale Simulation ohne Konto – nur wenn APP_ENV != production.
        'fake' => [
            'label' => 'Test-Zahlung (Simulation)',
            'enabled' => (bool) env('PAYMENTS_FAKE_ENABLED', true),
        ],
    ],
];
