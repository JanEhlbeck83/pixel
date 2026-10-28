<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Pixel Chaos | @yield('title')</title>
        <meta name="robots" content="index,follow">
        <x-locale-head />
        @vite(['resources/css/app.css'])
    </head>
    <body class="legal-page">
        <main class="legal-main">
            <a class="legal-back" href="{{ route('wall') }}">← {{ __('Zurück zur Wand') }}</a>

            <article class="legal-card">
                <h1>@yield('title')</h1>
                <p class="legal-meta">{{ __('Stand') }}: {{ \Illuminate\Support\Carbon::parse(config('legal.updated_at'))->locale(app()->getLocale())->isoFormat('LL') }}</p>

                @yield('content')
            </article>

            @include('legal.partials.links')
        </main>
    </body>
</html>
