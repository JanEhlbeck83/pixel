<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Pixel Chaos | Test-Zahlung</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="profile-page">
        <div class="pixel-shell">
            <main class="profile-main fake-pay">
                <section class="profile-card">
                    <h1 class="profile-title">Test-Zahlung (Simulation)</h1>
                    <p class="profile-muted">Dies ist kein echter Zahlungsanbieter – es wird kein Geld bewegt.</p>
                    <p><strong>{{ $order->title() }}</strong></p>
                    <p class="fake-pay-amount">{{ $order->priceFormatted() }}</p>
                    <form method="POST" action="{{ route('shop.fake.pay', $order) }}" class="fake-pay-actions">
                        @csrf
                        <button type="submit" name="result" value="paid" class="primary-button">Zahlung erfolgreich simulieren</button>
                        <button type="submit" name="result" value="cancel" class="secondary-button">Abbrechen</button>
                    </form>
                </section>
            </main>
        </div>
    </body>
</html>
