<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Pixel Chaos | Galerie</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="pixel-shell">
            <header class="pixel-topbar">
                <div class="brand">
                    <small>Archive</small>
                    <strong>PIXEL CHAOS</strong>
                </div>
                <div class="top-actions">
                    <a href="{{ route('wall') }}">Zur Wand</a>
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                </div>
            </header>

            <main>
                <div class="panel">
                    <h3>Automatische Schnappschüsse</h3>
                    <div class="gallery-grid">
                        @forelse ($snapshots as $snapshot)
                            <article class="gallery-item">
                                @if (!empty($snapshot->image_path))
                                    <a href="{{ asset($snapshot->image_path) }}" target="_blank" rel="noopener">
                                        <img src="{{ asset($snapshot->image_path) }}" alt="{{ $snapshot->label }}" class="snapshot-image" loading="lazy">
                                    </a>
                                @else
                                    <div class="mini-wall" aria-label="Schnappschuss Vorschau">
                                        <span></span>
                                    </div>
                                @endif
                                <h4>{{ $snapshot->label }}</h4>
                                <p>
                                    @php
                                        $minutesAgo = max(0, (int) floor(now()->diffInMinutes($snapshot->created_at)));
                                    @endphp
                                    @if ($minutesAgo === 0)
                                        gerade eben
                                    @elseif ($minutesAgo === 1)
                                        vor 1 Minute
                                    @else
                                        vor {{ $minutesAgo }} Minuten
                                    @endif
                                </p>
                                @if (!empty($snapshot->image_path))
                                    <a class="primary-button snapshot-download" href="{{ asset($snapshot->image_path) }}" download="pixel-chaos-{{ basename($snapshot->image_path) }}">⬇ Als PNG herunterladen @if (!empty($snapshot->summary['width']))({{ $snapshot->summary['width'] }} × {{ $snapshot->summary['height'] }} px)@endif</a>
                                @endif
                            </article>
                        @empty
                            <p>Keine Schnappschüsse vorhanden.</p>
                        @endforelse
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
