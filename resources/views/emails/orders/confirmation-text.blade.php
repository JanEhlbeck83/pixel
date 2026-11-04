{{ $labels['title'] }}

{{ $labels['intro'] }}

{{ $labels['order'] }}: {{ $order->uuid }}
{{ $labels['date'] }}: {{ now()->timezone(config('app.timezone'))->format('Y-m-d H:i T') }}
{{ $labels['product'] }}: {{ $productTitle }}
{{ $labels['amount'] }}: {{ $price }}
{{ $labels['payment'] }}: {{ ucfirst($order->provider) }}

{{ $labels['vat'] }}

{{ $labels['consent'] }}:
{{ $consentText }}

{{ $labels['terms'] }}: {{ $termsUrl }}

{{ $labels['seller'] }}:
{{ $seller['name'] }}
{{ $seller['street'] }}
{{ $seller['zip_city'] }}
{{ $seller['country'] }}
{{ $seller['email'] }}

{{ $labels['footer'] }}
