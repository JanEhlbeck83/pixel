<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ __('Gruppenfreigaben') }} | PixRiot</title>
        <x-favicons />
        <x-locale-head />
        @vite(['resources/css/app.css'])
    </head>
    <body class="menu-page">
        <div class="pixel-shell">
            @include('partials.site-menu')
            <header class="page-heading"><div class="brand"><small>{{ __('Administration') }}</small><a class="brand-wordmark" href="{{ route('home') }}" aria-label="{{ __('PixRiot – zur Wand') }}">Pix<span>Riot</span></a></div></header>
            <main class="panel community-panel">
                <h1>{{ __('Gruppenfreigaben') }}</h1>
                @if (session('status'))<p class="community-status" role="status">{{ session('status') }}</p>@endif
                <div class="community-group-grid">
                    @forelse ($groups as $group)
                        <article class="community-card">
                            <h2>{{ $group->name }}</h2>
                            <p>{{ $group->description }}</p>
                            <p>
                                @if ($group->founder)
                                    {{ __('Antrag von :name', ['name' => $group->founder->name]) }}
                                @else
                                    {{ __('PixRiot-Startgruppe') }}
                                @endif
                                · {{ __($group->status) }}
                            </p>
                            @if ($group->admin_note)<p>{{ $group->admin_note }}</p>@endif
                            @if ($group->status === 'pending')
                                <form method="POST" action="{{ route('admin.community-groups.approve', $group) }}">@csrf<button class="primary-button" type="submit">{{ __('Gruppe freigeben') }}</button></form>
                                <form method="POST" action="{{ route('admin.community-groups.reject', $group) }}" class="community-form">
                                    @csrf
                                    <label>{{ __('Ablehnungsgrund (optional)') }}<textarea name="admin_note" maxlength="1000"></textarea></label>
                                    <button class="secondary-button" type="submit">{{ __('Ablehnen') }}</button>
                                </form>
                            @endif
                        </article>
                    @empty
                        <p>{{ __('Es liegen keine Gruppenanträge vor.') }}</p>
                    @endforelse
                </div>
                {{ $groups->links() }}
            </main>
            @include('legal.partials.links')
        </div>
    </body>
</html>
