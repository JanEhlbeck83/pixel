<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Pixel Chaos | {{ request()->routeIs('register') ? 'Registrieren' : 'Anmelden' }}</title>

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
                    <a class="auth-brand" href="{{ route('wall') }}" aria-label="Pixel Chaos – zur Wand">
                        <span class="auth-brand-mark" aria-hidden="true">
                            <i></i><i></i><i></i><i></i>
                        </span>
                        <span>PIXEL<span class="auth-brand-accent">CHAOS</span></span>
                    </a>
                </div>

                <div class="auth-top-link">
                    <span>{{ $isRegistering ? 'Schon Teil der Community?' : 'Noch kein Konto?' }}</span>
                    <a href="{{ $isRegistering ? route('login') : route('register') }}">
                        {{ $isRegistering ? 'Anmelden' : 'Konto erstellen' }}
                    </a>
                </div>

                <section class="auth-card" aria-labelledby="auth-title">
                    <div class="auth-card-heading">
                        <span class="auth-eyebrow">{{ $isRegistering ? 'Werde Teil davon' : 'Schön, dass du da bist' }}</span>
                        <h2 id="auth-title">{{ $isRegistering ? 'Dein Platz auf der Wand.' : 'Willkommen zurück.' }}</h2>
                        <p>{{ $isRegistering ? 'Erstelle dein Konto und setz deinen ersten Pixel.' : 'Melde dich an und mach weiter, wo du aufgehört hast.' }}</p>
                    </div>

                    {{ $slot }}
                </section>

                <p class="auth-privacy-note"><span aria-hidden="true">✳</span> Gemeinsam kreativ. Pixel für Pixel.</p>
            </main>
        </div>
    </body>
</html>
