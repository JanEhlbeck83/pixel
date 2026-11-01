<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('PixRiot | Galerie') }}</title>
        <x-favicons />
        <x-locale-head />
        <x-i18n-script />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="menu-page">
        <div class="pixel-shell">
            @include('partials.site-menu')
            <header class="page-heading">
                <div class="brand">
                    <small>{{ __('Archive') }}</small>
                    <strong class="brand-wordmark">Pix<span>Riot</span></strong>
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
                                        <img src="{{ asset($snapshot->thumbnail_path) }}" alt="{{ __('Schnappschuss: :label', ['label' => $snapshot->label]) }}" class="snapshot-image" loading="lazy">
                                    </a>
                                @else
                                    <div class="mini-wall" aria-label="{{ __('Schnappschuss Vorschau') }}">
                                        <span></span>
                                    </div>
                                @endif
                                <h4>{{ __($snapshot->label) }}</h4>
                                @if (!empty($snapshot->image_path))
                                    <a class="snapshot-download" href="{{ asset($snapshot->image_path) }}" download="pixriot-{{ basename($snapshot->image_path) }}"><x-icon name="download" />{{ __('Als PNG herunterladen') }} @if (!empty($snapshot->summary['width']))({{ $snapshot->summary['width'] }} × {{ $snapshot->summary['height'] }} px)@endif</a>
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
