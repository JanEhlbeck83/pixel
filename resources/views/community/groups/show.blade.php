<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $group->name }} | PixRiot</title>
        <x-favicons />
        <x-locale-head />
        @vite(['resources/css/app.css', 'resources/js/community-project.js'])
    </head>
    <body class="menu-page">
        <div class="pixel-shell">
            @include('partials.site-menu')
            <header class="page-heading community-page-heading">
                <div class="brand"><small><a href="{{ route('community.index') }}">{{ __('Community') }}</a></small><a class="brand-wordmark" href="{{ route('home') }}" aria-label="{{ __('PixRiot – zur Wand') }}">Pix<span>Riot</span></a></div>
                @include('community.navigation', ['showGroupLink' => false])
            </header>
            <main>
                <section class="panel community-panel">
                    @if (session('status'))<p class="community-status" role="status">{{ session('status') }}</p>@endif
                    @if ($errors->any())<div class="community-errors" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
                    @if ($membership?->community_group_id !== $group->id && $group->status === 'approved')
                        @include('community.groups.card')
                    @else
                    <div class="community-group-title">
                        @if ($group->emblem)
                            <img class="community-group-emblem" src="{{ asset('images/community/emblems/'.$group->emblem.'.svg') }}" alt="{{ __('Emblem der Gruppe :name', ['name' => $group->name]) }}" width="88" height="88">
                        @endif
                        <h1>{{ $group->name }}</h1>
                    </div>
                    <p>{{ $group->description }}</p>
                    <div class="community-group-primer">
                        <x-icon name="community" />
                        <p>{{ __('Projekt vorstellen, direkt anmelden und gemeinsam loslegen – ohne Abstimmung.') }}</p>
                    </div>
                    <p>{{ __('Innerhalb von 24 Stunden muss die Mindestbesetzung erreicht sein. Dann startet das Projekt sofort oder zum geplanten Zeitpunkt.') }}</p>
                    <a class="community-gallery-link" href="{{ route('community.groups.gallery', $group) }}">{{ __('Abgeschlossene Projekte ansehen') }}</a>
                    @if ($group->status === 'pending')
                        <p class="community-status">{{ __('Diese Gruppe wartet auf die Freigabe durch den Admin.') }}</p>
                    @else
                        <p>
                            @if ($group->founder)
                                {{ __('Gegründet von :name · :count Mitglieder', ['name' => $group->founder->name, 'count' => $group->members_count]) }}
                            @else
                                {{ __('PixRiot-Startgruppe · :count Mitglieder', ['count' => $group->members_count]) }}
                            @endif
                        </p>
                        @if (! $membership && auth()->check())
                            @if ($groupSwitchAvailableAt?->isFuture() && (int) $lastLeftGroupId !== (int) $group->id)
                                <p class="community-switch-notice" role="note">{{ __('Nach einem freiwilligen Austritt gilt eine Wechselpause von 7 Tagen. Der Beitritt ist wieder möglich ab :date.', ['date' => $groupSwitchAvailableAt->format('d.m.Y H:i')]) }}</p>
                            @else
                                <form method="POST" action="{{ route('community.groups.join', $group) }}" class="community-join-form">
                                    @csrf
                                    <p class="community-switch-notice" role="note">{{ __('Nach einem freiwilligen Austritt kannst du 7 Tage lang keiner anderen Gruppe beitreten. In deine zuletzt verlassene Gruppe kannst du sofort zurückkehren.') }}</p>
                                    <label class="community-join-confirm">
                                        <input type="checkbox" name="confirm_group_switch_cooldown" value="1" required>
                                        <span>{{ __('Ich habe die 7-tägige Wechselpause verstanden.') }}</span>
                                    </label>
                                    <button class="primary-button" type="submit">{{ __('Gruppe beitreten') }}</button>
                                </form>
                            @endif
                        @endif
                        @if ($membership?->community_group_id === $group->id)
                            <p>{{ __(':count von 5 Projektplätzen belegt', ['count' => $runningProjectCount]) }}</p>
                            @if ($hasOwnRunningProject)
                                <p>{{ __('Du leitest bereits ein Projekt oder bist für eines angemeldet.') }}</p>
                            @elseif ($runningProjectCount < 5)
                                <details class="community-create">
                                    <summary class="primary-button">{{ __('Projekt vorschlagen') }}</summary>
                                    <form method="POST" action="{{ route('community.projects.store', $group) }}" enctype="multipart/form-data" class="community-form">
                                        @csrf
                                        <label>{{ __('Projekttitel') }}<input name="title" maxlength="100" minlength="3" required></label>
                                        <label>{{ __('Projekttyp') }}
                                            <select name="type" required>
                                                <option value="template">{{ __('Nach eigener Vorlage') }}</option>
                                                <option value="free">{{ __('Freies Projekt (Thema ohne feste Vorlage)') }}</option>
                                            </select>
                                        </label>
                                        <div class="community-size-fields">
                                            <label>{{ __('Breite (32–200 px)') }}<input type="number" name="width" min="32" max="200" value="{{ old('width', 128) }}" required></label>
                                            <label>{{ __('Höhe (32–200 px)') }}<input type="number" name="height" min="32" max="200" value="{{ old('height', 128) }}" required></label>
                                        </div>
                                        <label>{{ __('Benötigte Mitglieder einschließlich Projektleitung') }}<input type="number" name="minimum_participants" min="3" max="1000" value="{{ old('minimum_participants', 3) }}" required></label>
                                        <label>{{ __('Erwartete Arbeitsdauer') }}
                                            <select name="expected_duration_hours" required>
                                                @foreach ([1 => '1 Stunde', 3 => '3 Stunden', 6 => '6 Stunden', 12 => '12 Stunden', 24 => '1 Tag', 48 => '2 Tage', 72 => '3 Tage', 168 => '7 Tage'] as $hours => $label)
                                                    <option value="{{ $hours }}" @selected((int) old('expected_duration_hours', 24) === $hours)>{{ __($label) }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label>{{ __('Startzeitpunkt (leer = sofort bei Mindestbesetzung)') }}<input type="datetime-local" name="scheduled_start_at" value="{{ old('scheduled_start_at') }}"></label>
                                        <p>{{ __('Datum und Uhrzeit in :timezone; höchstens sieben Tage im Voraus.', ['timezone' => config('app.timezone')]) }}</p>
                                        <label>{{ __('Eigene Vorlage (bei Vorlagenprojekt erforderlich)') }}<input type="file" name="image" accept="image/png,image/jpeg,image/webp"></label>
                                        <p>{{ __('Die Vorlage muss mindestens so groß wie die Projektfläche sein. Die erwartete Dauer ist eine Orientierung; spätestens sieben Tage nach Start muss abgeschlossen sein.') }}</p>
                                        @include('community.projects.rules')
                                        <button class="primary-button" type="submit">{{ __('Teilnehmersuche starten') }}</button>
                                    </form>
                                </details>
                            @else
                                <p>{{ __('Diese Gruppe hat bereits fünf laufende Projekte.') }}</p>
                            @endif
                        @endif
                    @endif
                    @endif
                </section>
                @if ($membership?->community_group_id === $group->id)
                <section class="panel community-panel">
                    <h2>{{ __('Projekte') }}</h2>
                    <div class="community-group-grid">
                        @forelse ($projects as $project)
                            <article class="community-card community-project-card">
                                <div>
                                <h3><a href="{{ route('community.projects.show', $project) }}">{{ $project->title }}</a></h3>
                                @php($projectStatusLabels = \App\Models\CommunityProject::STATUS_LABELS)
                                <p>{{ __('Projektphase') }}: {{ __($projectStatusLabels[$project->status] ?? 'Abgebrochen') }}</p>
                                @include('community.projects.plan')
                                @if ($project->status === 'published' && $project->image_path)
                                    <a href="{{ route('community.projects.image', $project) }}"><img class="community-project-thumbnail" src="{{ route('community.projects.image', $project) }}" alt="{{ $project->title }}" loading="lazy"></a>
                                @endif
                                </div>
                                <div class="community-project-actions">
                                    <a class="secondary-button" href="{{ route('community.projects.show', $project) }}">{{ __('Projekt ansehen') }}</a>
                                    @if ($membership?->community_group_id === $group->id && ! $project->is_participant && $project->isOpenForJoining())
                                        <form method="POST" action="{{ route('community.projects.join', $project) }}">
                                            @csrf
                                            <button class="primary-button" type="submit" @disabled($hasOwnRunningProject)>{{ __('Für Projekt anmelden') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <p>{{ __('Diese Gruppe hat noch keine Projekte gestartet.') }}</p>
                        @endforelse
                    </div>
                    {{ $projects->links() }}
                </section>
                @endif
            </main>
            @include('legal.partials.links')
        </div>
    </body>
</html>
