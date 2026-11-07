<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Pixelgalerie von :name', ['name' => $user->name]) }} | PixRiot</title>
        <x-favicons />
        <x-locale-head />
        @vite(['resources/css/app.css'])
    </head>
    <body class="profile-page menu-page">
        <div class="pixel-shell">
            @include('partials.site-menu')
            <header class="page-heading">
                <a class="brand-wordmark" href="{{ route('home') }}">Pix<span>Riot</span></a>
            </header>
            <main class="profile-main">
                <h1>{{ __('Pixelgalerie von :name', ['name' => $user->name]) }}</h1>
                @if (session('status'))
                    <p role="status">{{ __('Galerie aktualisiert.') }}</p>
                @endif
                @if ($owner)
                    <p>{{ __('Neue Bilder sind zunächst privat. Öffentlich geschaltete Bilder sind für alle sichtbar. Das Löschen hier entfernt nur den Galerieeintrag, nicht die Pixel auf der Wall.') }}</p>
                    <p><a class="primary-button" href="{{ route('home') }}#draft">{{ __('Am Entwurf weitermalen') }}</a> <a class="secondary-button" href="{{ route('profile.edit') }}">{{ __('Mein Profil') }}</a></p>
                @endif
                <div class="gallery-grid artwork-grid">
                    @forelse ($artworks as $artwork)
                        @include('profile.partials.artwork', ['artworkOwner' => $owner])
                    @empty
                        <p>{{ __('Noch keine Bilder sichtbar.') }}</p>
                    @endforelse
                </div>
                {{ $artworks->links() }}
            </main>
            @include('legal.partials.links')
        </div>
    </body>
</html>
