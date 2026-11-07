<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="analytics-heartbeat-url" content="{{ route('analytics.heartbeat') }}">
        <title>{{ __('Pixelart – Community-Kunstwerke & Snapshots | PixRiot') }}</title>
        <meta name="description" content="{{ __('Entdecke die Kunstwerke der PixRiot-Community. Regelmäßige Snapshots zeigen, wie sich unsere gemeinsame Pixelwand verändert.') }}">
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
                    <a class="brand-wordmark" href="{{ route('home') }}" aria-label="{{ __('PixRiot – zur Wand') }}">Pix<span>Riot</span></a>
                </div>
            </header>

            <main>
                <section class="panel">
                    <h2>{{ __('Pixelbilder der Community') }}</h2>
                    <div class="gallery-grid artwork-grid">
                        @forelse ($artworks as $artwork)
                            @include('profile.partials.artwork', ['artworkOwner' => false])
                        @empty
                            <p>{{ __('Noch keine Bilder sichtbar.') }}</p>
                        @endforelse
                    </div>
                </section>
                <div class="panel">
                    <h1>{{ __('Kunstwerke im Wandel') }}</h1>
                    <p>{{ __('Snapshots unserer gemeinsamen Pixelwand.') }}</p>
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
                <section class="panel">
                    <h2>{{ __('Community-Projekte') }}</h2>
                    <p>{{ __('Gemeinsam von Gruppen gestaltete Pixelart, veröffentlicht auf der großen Leinwand.') }}</p>
                    <div class="gallery-grid">
                        @forelse ($communityProjects as $project)
                            <article class="gallery-item">
                                <a href="{{ route('community.projects.show', $project) }}">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($project->image_path) }}" alt="{{ $project->title }}" class="community-project-thumbnail" loading="lazy">
                                </a>
                                <h3><a href="{{ route('community.projects.show', $project) }}">{{ $project->title }}</a></h3>
                                <p>{{ __('Gruppe') }}: <a href="{{ route('community.groups.show', $project->group) }}">{{ $project->group->name }}</a></p>
                                <small>{{ $project->width }} × {{ $project->height }} px · {{ $project->published_at->format('d.m.Y') }}</small>
                            </article>
                        @empty
                            <p>{{ __('Noch keine Community-Projekte veröffentlicht.') }}</p>
                        @endforelse
                    </div>
                </section>
            </main>
            @include('legal.partials.links')
        </div>
    </body>
</html>
