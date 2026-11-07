<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ __('PixRiot | Analyse') }}</title>
        <x-favicons />
        <x-locale-head />
        <x-i18n-script />
        @vite(['resources/css/app.css'])
    </head>
    <body class="menu-page">
        <div class="pixel-shell">
            @include('partials.site-menu')
            <header class="page-heading">
                <div class="brand">
                    <small>{{ __('Admin') }}</small>
                    <a class="brand-wordmark" href="{{ route('home') }}" aria-label="{{ __('PixRiot – zur Wand') }}">Pix<span>Riot</span></a>
                </div>
            </header>

            <main class="analytics-page">
                <section class="analytics-cards" aria-label="{{ __('Übersicht') }}">
                    @foreach ($summary as $period)
                        <article class="analytics-card">
                            <h2>{{ __($period['label']) }}</h2>
                            <p><strong>{{ number_format($period['visitors'], 0, ',', '.') }}</strong> {{ __('Besuche') }}</p>
                            <p>{{ number_format($period['pixels'], 0, ',', '.') }} {{ __('gesetzte Pixel') }}</p>
                            <p>{{ __('Ø aktive Verweildauer') }}:
                                @if ($period['duration'] >= 60)
                                    {{ intdiv($period['duration'], 60) }} min {{ $period['duration'] % 60 }} s
                                @else
                                    {{ $period['duration'] }} s
                                @endif
                            </p>
                        </article>
                    @endforeach
                </section>

                <section class="analytics-panels">
                    <div class="panel">
                        <h2>{{ __('Länder (letzte 30 Tage)') }}</h2>
                        @forelse ($countries as $country)
                            <div class="analytics-country">
                                <span>{{ $country['name'] }}</span>
                                <strong>{{ number_format($country['visits'], 0, ',', '.') }}</strong>
                            </div>
                        @empty
                            <p>{{ __('Noch keine Länderangaben verfügbar.') }}</p>
                        @endforelse
                    </div>

                    <div class="panel">
                        <h2>{{ __('Tägliche Besuche (letzte 30 Tage)') }}</h2>
                        <div class="profile-table-wrap">
                            <table class="nl-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Datum') }}</th>
                                        <th>{{ __('Besuche') }}</th>
                                        <th>{{ __('gesetzte Pixel') }}</th>
                                        <th>{{ __('Ø aktive Zeit') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($days as $day)
                                        <tr>
                                            <td>{{ \Illuminate\Support\Carbon::parse($day['date'])->locale(app()->getLocale())->translatedFormat('d.m.Y') }}</td>
                                            <td>{{ number_format($day['visitors'], 0, ',', '.') }}</td>
                                            <td>{{ number_format($day['pixels'], 0, ',', '.') }}</td>
                                            <td>
                                                @if ($day['duration'] >= 60)
                                                    {{ intdiv($day['duration'], 60) }} min {{ $day['duration'] % 60 }} s
                                                @else
                                                    {{ $day['duration'] }} s
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <p class="report-meta analytics-note">
                    {{ __('Besuche werden pro Tag mit einer wechselnden Hash-Kennung gezählt. Die aktive Zeit wird nur bei sichtbarer Seite gezählt; Länder sind eine ungefähre lokale Geo-IP-Erkennung. Analysedaten werden nach 90 Tagen gelöscht.') }}
                </p>
            </main>
            @include('legal.partials.links')
        </div>
    </body>
</html>
