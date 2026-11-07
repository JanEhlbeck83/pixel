<article class="gallery-item artwork-card">
    <a href="{{ route('artworks.image', $artwork) }}" target="_blank" rel="noopener">
        <img class="artwork-image" src="{{ route('artworks.image', $artwork) }}" width="100" height="250" alt="{{ $artwork->title }}" loading="lazy">
    </a>
    <h3>{{ $artwork->title }}</h3>
    <p><a href="{{ route('artworks.index', $artwork->user_id) }}">{{ __('Pixelgalerie von :name', ['name' => $artwork->user->name]) }}</a></p>
    <small>20 × 50 px · {{ $artwork->created_at->format('d.m.Y') }} · X {{ $artwork->wall_x }}, Y {{ $artwork->wall_y }}</small>
    @if ($artworkOwner)
        <p>{{ $artwork->is_public ? __('Öffentlich') : __('Privat') }}</p>
        <form method="POST" action="{{ route('artworks.update', $artwork) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="is_public" value="{{ $artwork->is_public ? '0' : '1' }}">
            <button type="submit" class="secondary-button">{{ $artwork->is_public ? __('Privat schalten') : __('Öffentlich machen') }}</button>
        </form>
        <form method="POST" action="{{ route('artworks.destroy', $artwork) }}" onsubmit="return confirm(@js(__('Dieses Bild aus deiner Galerie löschen? Die Wall bleibt unverändert.')))">
            @csrf
            @method('DELETE')
            <button type="submit" class="secondary-button">{{ __('Aus Galerie löschen') }}</button>
        </form>
    @endif
</article>
