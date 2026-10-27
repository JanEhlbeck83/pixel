<?php

/*
|--------------------------------------------------------------------------
| Pixel Chaos – Zahlungen & Shop
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
        'pixels_100' => [
            'type' => 'pixels',
            'amount' => 100,
            'price_cents' => (int) env('PRICE_PIXELS_100_CENTS', 500),
        ],
        // Spar-Paket: 0,03 € statt 0,05 € pro Pixel
        'pixels_500' => [
            'type' => 'pixels',
            'amount' => 500,
            'price_cents' => (int) env('PRICE_PIXELS_500_CENTS', 1500),
        ],
    ],

    // Spielregeln für das Setzen von Pixeln
    'limits' => [
        // Gäste: einmaliger Vorrat pro IP, danach nur mit Konto.
        'guest_pixels' => (int) env('GUEST_PIXELS', 50),
        // Konten: Vorrat (Maximum) und Nachwachsen. Mehr als das Maximum gibt es nur gekauft.
        'user_max_free_pixels' => (int) env('USER_MAX_FREE_PIXELS', 50),
        'user_regen_seconds' => (int) env('USER_REGEN_SECONDS', 30),
    ],

    'providers' => [

        // PayPal (inkl. Lastschrift/Bankkonto und Kartenzahlung für PayPal-Kunden)
        // Sandbox-Zugang: https://developer.paypal.com/dashboard/applications/sandbox
        'paypal' => [
            'label' => 'PayPal',
            'mode' => env('PAYPAL_MODE', 'sandbox'),
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        ],

        // Stripe Checkout (Karten, Apple Pay, Google Pay, SEPA-Lastschrift, Klarna ...
        // je nachdem, was im Stripe-Dashboard unter "Zahlungsmethoden" aktiviert ist)
        // Testschlüssel (sk_test_...): https://dashboard.stripe.com/test/apikeys
        'stripe' => [
            'label' => 'Karte / Apple Pay / SEPA (Stripe)',
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],

        // Mollie (iDEAL, Kreditkarte, PayPal, SEPA, Klarna, Apple Pay ...)
        // Ein Schlüssel "test_..." = Sandbox, "live_..." = echt.
        // API-Key: https://my.mollie.com/dashboard/developers/api-keys
        'mollie' => [
            'label' => 'Mollie (Karte, Klarna, SEPA, …)',
            'api_key' => env('MOLLIE_API_KEY'),
        ],

        // Lokale Simulation ohne Konto – nur wenn APP_ENV != production.
        'fake' => [
            'label' => 'Test-Zahlung (Simulation)',
            'enabled' => (bool) env('PAYMENTS_FAKE_ENABLED', true),
        ],
    ],
];
