<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ __('Projektgalerie') }}: {{ $group->name }} | PixRiot</title>
        <x-favicons />
        <x-locale-head />
        @vite(['resources/css/app.css'])
    </head>
    <body class="menu-page">
        <div class="pixel-shell">
            @include('partials.site-menu')
            <header class="page-heading community-page-heading">
                <div class="brand">
                    <small><a href="{{ route('community.groups.show', $group) }}">{{ $group->name }}</a></small>
                    <a class="brand-wordmark" href="{{ route('home') }}" aria-label="{{ __('PixRiot – zur Wand') }}">Pix<span>Riot</span></a>
                </div>
                @include('community.navigation')
            </header>
            <main class="panel community-panel">
                <h1>{{ __('Abgeschlossene Projekte') }} · {{ $group->name }}</h1>
                <p>{{ __('Alle von dieser Gruppe veröffentlichten Community-Projekte.') }}</p>
                <div class="gallery-grid">
                    @forelse ($projects as $project)
                        <article class="gallery-item">
                            <a href="{{ route('community.projects.show', $project) }}">
                                <img src="{{ route('community.projects.image', $project) }}" alt="{{ $project->title }}" class="community-project-thumbnail" loading="lazy">
                            </a>
                            <h2><a href="{{ route('community.projects.show', $project) }}">{{ $project->title }}</a></h2>
                            <small>{{ $project->width }} × {{ $project->height }} px · {{ $project->published_at->format('d.m.Y') }}</small>
                        </article>
                    @empty
                        <p>{{ __('Diese Gruppe hat noch keine Projekte veröffentlicht.') }}</p>
                    @endforelse
                </div>
                {{ $projects->links() }}
            </main>
            @include('legal.partials.links')
        </div>
    </body>
</html>
