<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Pixel Chaos | {{ request()->routeIs('register') ? __('Registrieren') : __('Anmelden') }}</title>
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
                    <a class="auth-brand" href="{{ route('wall') }}" aria-label="{{ __('Pixel Chaos – zur Wand') }}">
                        <span class="auth-brand-mark" aria-hidden="true">
                            <i></i><i></i><i></i><i></i>
                        </span>
                        <span>PIXEL<span class="auth-brand-accent">CHAOS</span></span>
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
                        <span class="auth-eyebrow">{{ $isRegistering ? __('Werde Teil davon') : __('Schön, dass du da bist') }}</span>
                        <h2 id="auth-title">{{ $isRegistering ? __('Dein Platz auf der Wand.') : __('Willkommen zurück.') }}</h2>
                        <p>{{ $isRegistering ? __('Erstelle dein Konto und setz deinen ersten Pixel.') : __('Melde dich an und mach weiter, wo du aufgehört hast.') }}</p>
                    </div>

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
