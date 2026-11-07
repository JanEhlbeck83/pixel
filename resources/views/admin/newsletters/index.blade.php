<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ __('PixRiot | Newsletter') }}</title>
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
                    <small>Newsletter</small>
                    <a class="brand-wordmark" href="{{ route('home') }}" aria-label="{{ __('PixRiot – zur Wand') }}">Pix<span>Riot</span></a>
                </div>
            </header>

            <main>
                <div class="panel">
                    <h3>Newsletter ({{ $newsletters->count() }})</h3>
                    <p class="report-meta">Aktuelle Abonnenten (Einwilligung, bestätigte E-Mail, nicht gesperrt): <strong>{{ $subscriberCount }}</strong></p>
                    @if (session('status'))
                        <p class="admin-flash" role="status">{{ session('status') }}</p>
                    @endif
                    <div class="nl-admin-actions">
                        <a class="primary-button" href="{{ route('admin.newsletters.create') }}">Neuer Newsletter</a>
                    </div>

                    @if ($newsletters->isNotEmpty())
                        <div class="profile-table-wrap">
                            <table class="nl-table">
                                <thead>
                                    <tr><th>#</th><th>Betreff</th><th>Vorlage</th><th>Status</th><th>Empfänger</th><th></th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($newsletters as $newsletter)
                                        <tr>
                                            <td>{{ $newsletter->id }}</td>
                                            <td>{{ $newsletter->subject }}</td>
                                            <td>{{ \App\Models\Newsletter::TEMPLATES[$newsletter->template] ?? $newsletter->template }}</td>
                                            <td>{{ $newsletter->isSent() ? 'Gesendet '.$newsletter->sent_at?->format('d.m.Y H:i') : ($newsletter->status === 'sending' ? 'Wird gesendet' : 'Entwurf') }}</td>
                                            <td>{{ $newsletter->recipient_count ?? '–' }}</td>
                                            <td><a class="secondary-button" href="{{ route('admin.newsletters.edit', $newsletter) }}">{{ $newsletter->status === 'draft' ? 'Bearbeiten' : 'Ansehen' }}</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </main>
            @include('legal.partials.links')
        </div>
    </body>
</html>
