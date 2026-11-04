<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $labels['title'] }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f5f8;color:#1f2430;font-family:Arial,Helvetica,sans-serif;line-height:1.55">
    <main style="max-width:640px;margin:0 auto;padding:28px;background:#fff;border-radius:12px">
        <h1 style="margin-top:0">{{ $labels['title'] }}</h1>
        <p>{{ $labels['intro'] }}</p>
        <table style="width:100%;border-collapse:collapse">
            <tr><th align="left" style="padding:8px 0">{{ $labels['order'] }}</th><td style="padding:8px 0">{{ $order->uuid }}</td></tr>
            <tr><th align="left" style="padding:8px 0">{{ $labels['date'] }}</th><td style="padding:8px 0">{{ now()->timezone(config('app.timezone'))->format('Y-m-d H:i T') }}</td></tr>
            <tr><th align="left" style="padding:8px 0">{{ $labels['product'] }}</th><td style="padding:8px 0">{{ $productTitle }}</td></tr>
            <tr><th align="left" style="padding:8px 0">{{ $labels['amount'] }}</th><td style="padding:8px 0">{{ $price }}</td></tr>
            <tr><th align="left" style="padding:8px 0">{{ $labels['payment'] }}</th><td style="padding:8px 0">{{ ucfirst($order->provider) }}</td></tr>
        </table>
        <p>{{ $labels['vat'] }}</p>
        <h2>{{ $labels['consent'] }}</h2>
        <blockquote style="margin:0;padding:12px 16px;border-left:4px solid #ff2d95;background:#f8f8fa">{{ $consentText }}</blockquote>
        <p><a href="{{ $termsUrl }}">{{ $labels['terms'] }}</a></p>
        <h2>{{ $labels['seller'] }}</h2>
        <p>
            {{ $seller['name'] }}<br>
            {{ $seller['street'] }}<br>
            {{ $seller['zip_city'] }}<br>
            {{ $seller['country'] }}<br>
            <a href="mailto:{{ $seller['email'] }}">{{ $seller['email'] }}</a>
        </p>
        <p>{{ $labels['footer'] }}</p>
    </main>
</body>
</html>
