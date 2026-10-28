<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Pixel Chaos</title>
        <x-locale-head />
        <x-i18n-script />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="pixel-shell">
            <header class="pixel-topbar">
                <div class="brand">
                    <small>{{ __('Live World') }}</small>
                    <strong>PIXEL CHAOS</strong>
                </div>
                <div class="top-actions">
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ route('wall') }}">{{ __('Wand betreten') }}</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit">{{ __('Abmelden') }}</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}">{{ __('Anmelden') }}</a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="primary-button">{{ __('Registrieren') }}</a>
                            @endif
                        @endauth
                    @endif
                </div>
            </header>

            <main class="hero">
                <section class="hero-card">
                    <div class="hero-grid">
                        <div class="hero-copy">
                            <span class="eyebrow">{{ __('Community Kunstwerk') }}</span>
                            <h1>{{ __('Mach das Internet kaputt.') }}</h1>
                            <p>
{{ __('Eine Pixel-Wand, die die Community gemeinsam gestaltet. Jeder setzt einen kleinen Farbpunkt, jeder hinterlässt eine Spur und jede Änderung bleibt im Verlauf sichtbar.') }}
                            </p>

                            <div class="hero-actions">
                                @auth
                                    <a href="{{ route('wall') }}" class="primary-button">{{ __('Zur Wand') }}</a>
                                @else
                                    <a href="{{ route('register') }}" class="primary-button">{{ __('Jetzt mitmachen') }}</a>
                                @endif
                                <a href="{{ route('gallery') }}" class="secondary-button">{{ __('Galerie ansehen') }}</a>
                            </div>

                            <div class="metric-row">
                                <div class="metric">
                                    <strong>{{ \App\Models\Pixel::count() }}</strong>
                                    <span>{{ __('beschriebene Pixel') }}</span>
                                </div>
                                <div class="metric">
                                    <strong>{{ \App\Models\User::count() }}</strong>
                                    <span>{{ __('Mitglieder') }}</span>
                                </div>
                                <div class="metric">
                                    <strong>{{ \App\Models\Activity::whereDate('created_at', today())->count() }}</strong>
                                    <span>{{ __('heute aktiv') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="hero-visual">
                            <div class="pixel-preview" aria-label="{{ __('Pixel preview') }}">
                                @for ($row = 0; $row < 16; $row++)
                                    @for ($col = 0; $col < 16; $col++)
                                        <span style="background: {{ ['#ff6b6b','#ffd93d','#48dbfb','#1dd1a1','#c56cf0','#ff9ff3','#f368e0','#00d2d3'][($row * 3 + $col) % 8] }};"></span>
                                    @endfor
                                @endfor
                            </div>
                        </div>
                    </div>
                </section>
            </main>
            @include('legal.partials.links')
        </div>
    </body>
</html>
