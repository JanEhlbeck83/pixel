<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Pixel Chaos | {{ __('Test-Zahlung') }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="profile-page">
        <div class="pixel-shell">
            <main class="profile-main fake-pay">
                <section class="profile-card">
                    <h1 class="profile-title">{{ __('Test-Zahlung (Simulation)') }}</h1>
                    <p class="profile-muted">{{ __('Dies ist kein echter Zahlungsanbieter – es wird kein Geld bewegt.') }}</p>
                    <p><strong>{{ $order->title() }}</strong></p>
                    <p class="fake-pay-amount">{{ $order->priceFormatted() }}</p>
                    <form method="POST" action="{{ route('shop.fake.pay', $order) }}" class="fake-pay-actions">
                        @csrf
                        <button type="submit" name="result" value="paid" class="primary-button">{{ __('Zahlung erfolgreich simulieren') }}</button>
                        <button type="submit" name="result" value="cancel" class="secondary-button">{{ __('Abbrechen') }}</button>
                    </form>
                </section>
            </main>
        </div>
    </body>
</html>
