<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>PixRiot | {{ request()->routeIs('register') ? __('Registrieren') : __('Anmelden') }}</title>
        <x-favicons />
        <x-locale-head />

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css'])
    </head>
    <body class="auth-page">
        @php($isRegistering = request()->routeIs('register'))

        <div class="auth-layout">
            <main class="auth-main">
                <div class="auth-mobile-brand">
                    <a class="auth-brand" href="{{ route('home') }}" aria-label="{{ __('PixRiot – zur Wand') }}">
                        <span class="brand-wordmark">Pix<span>Riot</span></span>
                    </a>
                </div>

                <div class="auth-top-link">
                    <span>{{ $isRegistering ? __('Schon Teil der Community?') : __('Noch kein Konto?') }}</span>
                    <a href="{{ $isRegistering ? route('login') : route('register') }}">
                        {{ $isRegistering ? __('Anmelden') : __('Konto erstellen') }}
                    </a>
                </div>

                <section class="auth-card" aria-labelledby="auth-title">
                    <div class="auth-card-heading">
                        <span class="auth-eyebrow">{{ $isRegistering ? __('Werde Teil der PixRiot-Community') : __('Schön, dass du wieder da bist') }}</span>
                        <h2 id="auth-title">{{ $isRegistering ? __('Pixel setzen. Zusammen gestalten.') : __('Deine Gruppe wartet.') }}</h2>
                        <p>{{ $isRegistering ? __('Erstelle dein kostenloses Konto, tritt einer Gruppe bei und gestaltet euer eigenes Pixelart-Projekt.') : __('Melde dich an, um mit deiner Gruppe abzustimmen und gemeinsam an Projekten weiterzuarbeiten.') }}</p>
                    </div>

                    <aside class="auth-community-promo">
                        <div class="auth-community-promo-heading"><x-icon name="community" /><strong>{{ __('So wirst du Teil eines Community-Projekts') }}</strong></div>
                        <ol>
                            <li>{{ __('Einer Gruppe beitreten – du kannst nur einer Gruppe angehören.') }}</li>
                            <li>{{ __('Vorschläge einreichen und mit der Gruppe über das Motiv abstimmen.') }}</li>
                            <li>{{ __('Auf einer geschützten Projektleinwand gemeinsam pixeln und veröffentlichen.') }}</li>
                        </ol>
                        <a href="{{ route('community.index') }}">{{ __('Gruppen und Ablauf ansehen') }} <span aria-hidden="true">→</span></a>
                    </aside>

                    {{ $slot }}
                </section>

                <p class="auth-privacy-note"><span aria-hidden="true">✳</span> {{ __('Gemeinsam kreativ. Pixel für Pixel.') }}</p>
                <p class="auth-legal-note">
                    {!! __('Mit der Anmeldung stimmst du den :terms zu und bestätigst die :privacy.', [
                        'terms' => '<a href="'.e(route('legal.terms')).'">'.e(__('Nutzungsbedingungen')).'</a>',
                        'privacy' => '<a href="'.e(route('legal.privacy')).'">'.e(__('Datenschutzerklärung')).'</a>',
                    ]) !!}
                </p>
                @include('legal.partials.links')
            </main>
        </div>
    </body>
</html>
