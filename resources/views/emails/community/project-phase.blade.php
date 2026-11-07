<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $emailLocale) }}">
    <body>
        <p>{{ $body }}</p>
        @if ($imagePath)
            <p><img src="{{ $message->embed($imagePath) }}" alt="{{ $imageAlt }}" style="display:block;max-width:100%;height:auto;image-rendering:pixelated"></p>
        @endif
    </body>
</html>
