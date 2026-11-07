<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ __('Gruppen | PixRiot') }}</title>
        <x-favicons />
        <x-locale-head />
        @vite(['resources/css/app.css', 'resources/js/community-project.js'])
    </head>
    <body class="menu-page">
        <div class="pixel-shell">
            @include('partials.site-menu')
            <header class="page-heading"><div class="brand"><small>{{ __('Zusammen gestalten') }}</small><a class="brand-wordmark" href="{{ route('home') }}" aria-label="{{ __('PixRiot – zur Wand') }}">Pix<span>Riot</span></a></div></header>
            <main>
                @if ($membership)
                    @if (session('status'))<p class="community-status" role="status">{{ session('status') }}</p>@endif
                    <section class="panel community-my-group" aria-labelledby="myCommunityGroupTitle">
                        <div class="community-my-group-main">
                            <div class="community-my-group-emblem">
                            @if ($membership->group->emblem)
                                <img class="community-group-emblem" src="{{ asset('images/community/emblems/'.$membership->group->emblem.'.svg') }}" alt="{{ __('Emblem der Gruppe :name', ['name' => $membership->group->name]) }}" width="88" height="88">
                            @endif
                            </div>
                            <div class="community-my-group-content">
                                <span class="community-eyebrow">{{ __('Deine Gruppe') }}</span>
                                <h1 id="myCommunityGroupTitle"><a href="{{ route('community.groups.show', $membership->group) }}">{{ $membership->group->name }}</a></h1>
                                <p>{{ $membership->group->description }}</p>
                                @if ($membership->last_active_at)
                                    <small class="community-my-group-last-active">{{ __('Zuletzt in der Gruppe aktiv: :time', ['time' => $membership->last_active_at->diffForHumans()]) }}</small>
                                @endif
                            </div>
                        </div>
                        <div class="community-my-group-overview">
                            <div class="community-my-group-stats">
                                <div><strong>{{ $activeGroupMembersCount }}</strong><span>{{ __('In den letzten 5 Minuten aktiv') }}</span></div>
                                <div><strong>{{ $membership->group->active_projects_count }}</strong><span>{{ __('Aktive Projekte') }}</span></div>
                                <div><strong>{{ $membership->group->completed_projects_count }}</strong><span>{{ __('Abgeschlossene Projekte') }}</span></div>
                            </div>
                            <p>{{ __(':count von 5 Projektplätzen belegt', ['count' => $membership->group->active_projects_count]) }}</p>
                            @forelse ($currentProjects as $listedProject)
                                <div class="community-my-group-current-project">
                                    <div class="community-my-group-project-copy">
                                        <span class="community-eyebrow">{{ __('Aktuelles Gruppenprojekt') }}</span>
                                        <a href="{{ route('community.projects.show', $listedProject) }}">{{ $listedProject->title }}</a>
                                        @php($projectPhaseLabels = \App\Models\CommunityProject::STATUS_LABELS)
                                        <small>{{ __($projectPhaseLabels[$listedProject->status] ?? 'Aktiv') }}</small>
                                        <small>{{ __('Angemeldet: :count · Benötigt: :minimum einschließlich Projektleitung', ['count' => $listedProject->participantCount(), 'minimum' => $listedProject->minimum_participants]) }}</small>
                                    </div>
                                    @if ($listedProject->status === 'active' && $listedProject->participants->isNotEmpty())
                                        <div class="community-project-preview" data-pixels-url="{{ route('community.projects.pixels', $listedProject) }}">
                                            <canvas width="{{ $listedProject->width }}" height="{{ $listedProject->height }}" aria-label="{{ __('Aktueller Stand von :project', ['project' => $listedProject->title]) }}"></canvas>
                                            <p class="community-project-preview-status" role="status" aria-live="polite"></p>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="community-my-group-no-project">{{ __('Noch kein laufendes Gruppenprojekt.') }}</p>
                            @endforelse
                        </div>
                        <div class="community-my-group-actions">
                            <a class="primary-button" href="{{ route('community.groups.show', $membership->group) }}">{{ __('Gruppe besuchen') }}</a>
                            @if ($currentProject)
                                @php($projectActionLabels = ['recruiting' => 'Für Projekt anmelden', 'scheduled' => 'Geplantes Projekt ansehen', 'active' => $isCurrentProjectParticipant ? 'Weiterpixeln' : 'Projekt ansehen'])
                                <a class="secondary-button" href="{{ route('community.projects.show', $currentProject) }}">{{ __($projectActionLabels[$currentProject->status] ?? 'Projekt ansehen') }}</a>
                            @else
                                <a class="secondary-button" href="{{ route('community.groups.show', $membership->group) }}">{{ __('Projekt starten') }}</a>
                            @endif
                            <form class="community-my-group-leave" method="POST" action="{{ route('community.groups.leave') }}" onsubmit="return confirm('{{ __('Möchtest du die Gruppe wirklich verlassen?') }}')">
                                @csrf
                                @method('DELETE')
                                <button class="secondary-button" type="submit">{{ __('Gruppe verlassen') }}</button>
                            </form>
                        </div>
                    </section>
                @endif
                <section class="panel community-panel">
                    @if (auth()->guest())
                        <p class="community-lead">{{ __('PixRiot ist mehr als eine Pixelwand: Schließt euch als Gruppe zusammen, entscheidet gemeinsam über Motive und erschafft Pixelart, die ihr zusammen auf der großen Leinwand veröffentlicht.') }}</p>
                        <ol class="community-workflow">
                            <li><span class="community-step-icon"><x-icon name="community" /></span><span><strong>{{ __('1. Gruppe finden oder gründen') }}</strong><small>{{ __('Tritt einer Gruppe bei. Eine eigene Gruppe kannst du einmal beantragen; der Admin schaltet sie frei.') }}</small></span></li>
                            <li><span class="community-step-icon"><x-icon name="community" /></span><span><strong>{{ __('2. Projekt gemeinsam planen') }}</strong><small>{{ __('Direkt auf der Gruppenseite anmelden. Innerhalb von 24 Stunden genügend Mitglieder finden; danach sofort oder zum geplanten Zeitpunkt starten.') }}</small></span></li>
                            <li><span class="community-step-icon"><x-icon name="wall" /></span><span><strong>{{ __('3. Zusammen gestalten und veröffentlichen') }}</strong><small>{{ __('Die Projektleitung veröffentlicht innerhalb von sieben Tagen ab Start. Die erwartete Dauer ist nur eine Orientierung.') }}</small></span></li>
                            <li><span class="community-step-icon community-step-icon--timer" aria-hidden="true">7w</span><span><strong>{{ __('4. Euer Werk auf der Wand halten') }}</strong><small>{{ __('Die Fläche bleibt nach Veröffentlichung sieben Wochen reserviert.') }}</small></span></li>
                        </ol>
                    @else
                        <div class="community-explanation" id="communityExplanation">
                            <div class="community-explanation-toggle">
                                <button class="secondary-button" type="button" id="toggleCommunityExplanation" aria-expanded="true" aria-controls="communityExplanationContent">
                                    {{ __('Erklärung einklappen') }}
                                </button>
                            </div>
                            <div id="communityExplanationContent">
                                <h2>{{ __('So läuft ein Gruppenprojekt ab') }}</h2>
                                <p class="community-lead">{{ __('PixRiot ist mehr als eine Pixelwand: Schließt euch als Gruppe zusammen, entscheidet gemeinsam über Motive und erschafft Pixelart, die ihr zusammen auf der großen Leinwand veröffentlicht.') }}</p>
                                <ol class="community-workflow">
                                    <li><span class="community-step-icon"><x-icon name="community" /></span><span><strong>{{ __('1. Gruppe finden oder gründen') }}</strong><small>{{ __('Tritt einer Gruppe bei. Eine eigene Gruppe kannst du einmal beantragen; der Admin schaltet sie frei.') }}</small></span></li>
                                    <li><span class="community-step-icon"><x-icon name="community" /></span><span><strong>{{ __('2. Projekt gemeinsam planen') }}</strong><small>{{ __('Direkt auf der Gruppenseite anmelden. Innerhalb von 24 Stunden genügend Mitglieder finden; danach sofort oder zum geplanten Zeitpunkt starten.') }}</small></span></li>
                                    <li><span class="community-step-icon"><x-icon name="wall" /></span><span><strong>{{ __('3. Zusammen gestalten und veröffentlichen') }}</strong><small>{{ __('Die Projektleitung veröffentlicht innerhalb von sieben Tagen ab Start. Die erwartete Dauer ist nur eine Orientierung.') }}</small></span></li>
                                    <li><span class="community-step-icon community-step-icon--timer" aria-hidden="true">7w</span><span><strong>{{ __('4. Euer Werk auf der Wand halten') }}</strong><small>{{ __('Die Fläche bleibt nach Veröffentlichung sieben Wochen reserviert.') }}</small></span></li>
                                </ol>
                            </div>
                        </div>
                        <script>
                            (() => {
                                const button = document.getElementById('toggleCommunityExplanation');
                                const content = document.getElementById('communityExplanationContent');
                                const storageKey = 'pixriot.communityExplanationCollapsed';
                                const updateState = (collapsed) => {
                                    content.hidden = collapsed;
                                    button.setAttribute('aria-expanded', String(!collapsed));
                                    button.textContent = collapsed
                                        ? @json(__('Erklärung ausklappen'))
                                        : @json(__('Erklärung einklappen'));
                                };
                                updateState(localStorage.getItem(storageKey) === '1');
                                button.addEventListener('click', () => {
                                    const collapsed = button.getAttribute('aria-expanded') === 'true';
                                    localStorage.setItem(storageKey, collapsed ? '1' : '0');
                                    updateState(collapsed);
                                });
                            })();
                        </script>
                    @endif
                    @if (! $membership && session('status'))<p class="community-status" role="status">{{ session('status') }}</p>@endif
                    @if ($errors->any())<div class="community-errors" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

                    @if (! $membership && auth()->guest())
                        <p class="community-auth-links">
                            <a href="{{ route('login') }}">{{ __('Anmelden') }}</a>
                            <span>{{ __('oder') }}</span>
                            <a href="{{ route('register') }}">{{ __('Registrieren') }}</a>
                            <span>{{ __('und bestätige deine E-Mail-Adresse, um einer Gruppe beizutreten oder eine zu gründen.') }}</span>
                        </p>
                    @endif
                </section>

                @if ($latestPublishedProjects->isNotEmpty() || $showcaseExamples->isNotEmpty())
                    <section class="panel community-latest" aria-label="{{ __('Projekt-Showcase') }}">
                        <div class="community-showcase-heading">
                            <h2>{{ __('Projekt-Showcase') }}</h2>
                            <div class="community-showcase-controls">
                                <button type="button" class="secondary-button" data-showcase-scroll="-1" aria-label="{{ __('Projekte nach links') }}">←</button>
                                <button type="button" class="secondary-button" data-showcase-scroll="1" aria-label="{{ __('Projekte nach rechts') }}">→</button>
                            </div>
                        </div>
                        @if ($latestPublishedProjects->isNotEmpty())
                            <div class="community-latest-grid" id="communityShowcase">
                            @foreach ($latestPublishedProjects as $project)
                                <a class="community-latest-item" href="{{ route('community.groups.gallery', $project->group) }}">
                                    <img src="{{ route('community.projects.image', $project) }}" alt="{{ $project->title }}" loading="lazy">
                                    <span>{{ $project->title }}</span>
                                    <small>{{ $project->group->name }} · {{ __('Galerie') }}</small>
                                </a>
                            @endforeach
                            </div>
                        @else
                            <div class="community-latest-grid" id="communityShowcase">
                                @foreach ($showcaseExamples as $example)
                                    <a class="community-latest-item community-latest-item--example" href="{{ route('community.groups.show', $example['group']) }}">
                                        <img src="{{ asset('images/community/showcase/'.$example['image']) }}" alt="{{ $example['title'] }} — {{ __('Beispielprojekt') }}" loading="lazy">
                                        <span>{{ $example['title'] }}</span>
                                        <small>{{ __('Beispielprojekt') }} · {{ $example['group']->name }}</small>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                        <script>
                            document.querySelectorAll('[data-showcase-scroll]').forEach((button) => {
                                button.addEventListener('click', () => {
                                    document.getElementById('communityShowcase').scrollBy({
                                        left: Number(button.dataset.showcaseScroll) * 300,
                                        behavior: 'smooth',
                                    });
                                });
                            });
                        </script>
                    </section>
                @endif

                <section class="panel community-panel">
                    <div class="community-section-heading">
                        <h1>{{ $membership ? __('Weitere Gruppen') : __('Gruppen') }}</h1>
                        @if (auth()->check() && ! $membership && ! $hasFoundedGroup)
                            <details class="community-create">
                                <summary class="primary-button">{{ __('Gruppe gründen') }}</summary>
                                <form method="POST" action="{{ route('community.groups.store') }}" class="community-form">
                                    @csrf
                                    <label>{{ __('Gruppenname') }}<input name="name" maxlength="60" minlength="3" required value="{{ old('name') }}"></label>
                                    <label>{{ __('Beschreibung') }}<textarea name="description" rows="4" minlength="10" maxlength="1000" required>{{ old('description') }}</textarea></label>
                                    <p>{{ __('Neue Gruppen werden vor der Veröffentlichung vom Admin geprüft. Jede Person kann eine Gruppe gründen.') }}</p>
                                    <button class="primary-button" type="submit">{{ __('Zur Prüfung einreichen') }}</button>
                                </form>
                            </details>
                        @endif
                    </div>
                    @if (auth()->check() && $hasFoundedGroup && ! $membership)
                        <p>{{ __('Dein Gruppenantrag wurde bereits eingereicht oder geprüft. Eine weitere Gruppe kannst du nicht gründen.') }}</p>
                    @endif
                    <div class="community-group-grid">
                        @forelse ($groups as $group)
                            @include('community.groups.card')
                        @empty
                            <p>{{ $membership ? __('Es gibt keine weiteren freigegebenen Gruppen.') : __('Es gibt noch keine freigegebenen Gruppen.') }}</p>
                        @endforelse
                    </div>
                    {{ $groups->links() }}
                </section>
            </main>
            @include('legal.partials.links')
        </div>
    </body>
</html>
