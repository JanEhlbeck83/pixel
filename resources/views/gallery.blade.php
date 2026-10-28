<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Pixel Chaos | Galerie') }}</title>
        <x-locale-head />
        <x-i18n-script />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="pixel-shell">
            <header class="pixel-topbar">
                <div class="brand">
                    <small>{{ __('Archive') }}</small>
                    <strong>PIXEL CHAOS</strong>
                </div>
                <div class="top-actions">
                    <a href="{{ route('wall') }}">{{ __('Zur Wand') }}</a>
                    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
                </div>
            </header>

            <main>
                <div class="panel">
                    <h3>{{ __('Automatische Schnappschüsse') }}</h3>
                    <div class="gallery-grid">
                        @forelse ($snapshots as $snapshot)
                            <article class="gallery-item">
                                @if (!empty($snapshot->image_path))
                                    <a href="{{ asset($snapshot->image_path) }}" target="_blank" rel="noopener">
                                        <img src="{{ asset($snapshot->image_path) }}" alt="{{ __('Schnappschuss: :label', ['label' => $snapshot->label]) }}" class="snapshot-image" loading="lazy">
                                    </a>
                                @else
                                    <div class="mini-wall" aria-label="{{ __('Schnappschuss Vorschau') }}">
                                        <span></span>
                                    </div>
                                @endif
                                <h4>{{ __($snapshot->label) }}</h4>
                                <p>
                                    @php
                                        $minutesAgo = max(0, (int) floor(now()->diffInMinutes($snapshot->created_at)));
                                    @endphp
                                    @if ($minutesAgo === 0)
                                        {{ __('gerade eben') }}
                                    @elseif ($minutesAgo === 1)
                                        {{ __('vor 1 Minute') }}
                                    @else
                                        {{ __('vor :minutes Minuten', ['minutes' => $minutesAgo]) }}
                                    @endif
                                </p>
                                @if (!empty($snapshot->image_path))
                                    <a class="primary-button snapshot-download" href="{{ asset($snapshot->image_path) }}" download="pixel-chaos-{{ basename($snapshot->image_path) }}">{{ __('⬇ Als PNG herunterladen') }} @if (!empty($snapshot->summary['width']))({{ $snapshot->summary['width'] }} × {{ $snapshot->summary['height'] }} px)@endif</a>
                                @endif
                            </article>
                        @empty
                            <p>{{ __('Keine Schnappschüsse vorhanden.') }}</p>
                        @endforelse
                    </div>
                </div>
            </main>
            @include('legal.partials.links')
        </div>
    </body>
</html>
