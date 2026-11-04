<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ __('PixRiot | Meldungen') }}</title>
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
                    <small>{{ __('Moderation') }}</small>
                    <strong class="brand-wordmark">Pix<span>Riot</span></strong>
                </div>
            </header>

            <main>
                <div class="panel">
                    <h3>{{ __('Offene Meldungen') }} ({{ $reports->count() }})</h3>
                    @if (session('status'))
                        <p class="admin-flash" role="status">{{ session('status') }}</p>
                    @endif

                    @forelse ($reports as $report)
                        <article class="report-item">
                            <img class="report-preview" src="{{ route('admin.reports.image', $report) }}" alt="" loading="lazy">
                            <div class="report-body">
                                <h4>#{{ $report->id }} · {{ __(\App\Models\Report::REASON_LABELS[$report->reason] ?? $report->reason) }}</h4>
                                <p class="report-meta">
                                    {{ __('Bereich') }} {{ $report->x }} : {{ $report->y }} · {{ $report->width }} × {{ $report->height }}
                                    · {{ $report->created_at->format('d.m.Y H:i') }}
                                    · {{ $report->reporter_email ?? __('anonym') }}
                                </p>
                                @if ($report->message)
                                    <p class="report-message">{{ $report->message }}</p>
                                @endif
                                <p class="report-meta">
                                    {{ __('Pixel zuletzt gesetzt von:') }}
                                    @forelse ($report->authors as $author)
                                        {{ $author->name }} ({{ $author->email }}){{ $author->banned_at ? ' – '.__('gesperrt') : '' }}@unless ($loop->last), @endunless
                                    @empty
                                        {{ __('Gäste oder niemand') }}
                                    @endforelse
                                </p>
                                <div class="report-actions">
                                    <a class="secondary-button" href="{{ route('home') }}?x={{ $report->x + intdiv($report->width, 2) }}&y={{ $report->y + intdiv($report->height, 2) }}&z=2" target="_blank" rel="noopener">{{ __('Ansehen') }}</a>
                                    <form method="POST" action="{{ route('admin.reports.reset', $report) }}" onsubmit="return confirm(@js(__('Bereich wirklich zurücksetzen?')))">
                                        @csrf
                                        <button type="submit" class="primary-button">{{ __('Bereich zurücksetzen') }}</button>
                                    </form>
                                    @if ($report->authors->isNotEmpty())
                                        <form method="POST" action="{{ route('admin.reports.reset-ban', $report) }}" onsubmit="return confirm(@js(__('Bereich zurücksetzen und die Nutzer sperren?')))">
                                            @csrf
                                            <button type="submit" class="primary-button">{{ __('Zurücksetzen + Nutzer sperren') }}</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.reports.dismiss', $report) }}">
                                        @csrf
                                        <button type="submit" class="secondary-button">{{ __('Ablehnen') }}</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    @empty
                        <p>{{ __('Keine offenen Meldungen.') }}</p>
                    @endforelse
                </div>

                @if ($handled->isNotEmpty())
                    <div class="panel">
                        <h3>{{ __('Zuletzt bearbeitet') }}</h3>
                        <ul class="report-history">
                            @foreach ($handled as $report)
                                <li>#{{ $report->id }} · {{ $report->x }}:{{ $report->y }} · {{ $report->action }} · {{ $report->handled_at?->format('d.m.Y H:i') }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </main>
            @include('legal.partials.links')
        </div>
    </body>
</html>
