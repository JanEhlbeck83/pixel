<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ __('PixRiot | Newsletter') }}</title>
        <x-favicons />
        <x-locale-head />
        @vite(['resources/css/app.css'])
    </head>
    <body class="menu-page">
        <div class="pixel-shell">
            <header class="page-heading">
                <div class="brand">
                    <small>Newsletter</small>
                    <strong class="brand-wordmark">Pix<span>Riot</span></strong>
                </div>
            </header>
            <main>
                <div class="panel">
                    <h3>{{ __('Newsletter abbestellt') }}</h3>
                    <p>{{ __('Du erhältst keine Newsletter mehr von PixRiot.') }}</p>
                    <p><a class="primary-button" href="{{ route('home') }}">{{ __('Zur Wand') }}</a></p>
                </div>
            </main>
        </div>
    </body>
</html>
