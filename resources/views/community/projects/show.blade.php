<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $isMember ? $project->title : __('Geheimes Gruppenprojekt') }} | PixRiot</title>
        <x-favicons />
        <x-locale-head />
        @vite(['resources/css/app.css', 'resources/js/community-project.js'])
    </head>
    <body class="menu-page community-project-page">
        <div class="pixel-shell">
            @include('partials.site-menu')
            <header class="page-heading community-page-heading">
                <div class="brand"><small><a href="{{ route('community.groups.show', $project->group) }}">{{ $project->group->name }}</a></small><a class="brand-wordmark" href="{{ route('home') }}">Pix<span>Riot</span></a></div>
                @include('community.navigation', ['group' => $project->group])
            </header>
            <main class="community-project-layout">
                <section class="panel community-panel">
                    @if (session('status'))<p class="community-status" role="status">{{ session('status') }}</p>@endif
                    @if ($errors->any())<div class="community-errors" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
                    @if (! $isMember)
                        <h1>{{ __('Hier entsteht ein Bild der Gruppe :name.', ['name' => $project->group->name]) }}</h1>
                        <p>{{ __('Die Projekte der Mitglieder sind geheim. Entwürfe und Vorlagen bleiben innerhalb der Gruppe; am gemeinsamen Bild arbeiten nur die Projektteilnehmenden.') }}</p>
                        <a class="primary-button" href="{{ route('community.groups.show', $project->group) }}">{{ __('Gruppe besuchen') }}</a>
                    @else
                        <h1>{{ __('Projekt: :title', ['title' => $project->title]) }}</h1>
                        <p>{{ __(\App\Models\CommunityProject::STATUS_LABELS[$project->status] ?? 'Archiviert') }}</p>
                        @include('community.projects.plan')
                        @if ($canJoinProject)
                            <form id="projectJoinForm" method="POST" action="{{ route('community.projects.join', $project) }}">@csrf<button class="primary-button">{{ __('Für Projekt anmelden') }}</button></form>
                        @elseif (! $participant && $project->isOpenForJoining())
                            <p>{{ __('Du leitest bereits ein Projekt oder bist für eines angemeldet.') }}</p>
                        @endif
                        @if ($participant)
                            <h2>{{ __('Teilnehmende') }}</h2>
                            <ul>@foreach ($project->participants as $registration)<li>{{ $registration->user?->name ?? __('Gelöschtes Konto') }} @if ($registration->user_id === $project->created_by) ({{ __('Projektleitung') }}) @endif</li>@endforeach</ul>
                        @endif
                        @include('community.projects.rules')
                    @endif
                    @include('community.projects.cancel')
                </section>
                @if ($isMember)
                    @if ($canReserveProject)
                        <section class="panel community-panel">
                            <h2>{{ __('Projektfläche auf der Wall platzieren') }}</h2>
                            <p>{{ __('Vor dem Malen reserviert die Projektleitung die gewählte Fläche auf der Wall. Der Startzeitpunkt und die Abschlussfrist bleiben unverändert.') }}</p>
                            <a class="primary-button" href="{{ route('home', ['reserve_project' => $project->id]) }}">{{ __('Auf der Wall platzieren') }}</a>
                        </section>
                    @endif
                    @if ($participant && $project->template_image_path)
                        <section class="panel community-panel">
                            <h2>{{ __('Gemeinsame Originalvorlage') }}</h2>
                            <p>{{ __('Die Vorlage ist eine Orientierung und wird nicht automatisch aufgemalt.') }}</p>
                            <div class="community-reference" data-image-url="{{ route('community.projects.template', $project) }}" data-load-error="{{ __('Das Vorlagenbild konnte nicht geladen werden.') }}" data-transparent="{{ __('Dieser Pixel ist transparent und hat keine übernehmbare Farbe.') }}">
                                <div class="community-canvas-zoom" data-community-zoom>
                                    @include('community.projects.canvas-zoom', ['zoomLabel' => __('Zoom der Vorlage'), 'pipette' => 'reference'])
                                    <div class="community-canvas-viewport" tabindex="0" aria-label="{{ __('Gemeinsame Originalvorlage') }}">
                                        <canvas width="{{ $project->width }}" height="{{ $project->height }}" tabindex="0" aria-label="{{ __('Gemeinsame Originalvorlage') }}"></canvas>
                                    </div>
                                </div>
                                <p data-reference-status role="status"></p>
                            </div>
                            @if ($isProjectLeader && in_array($project->status, \App\Models\CommunityProject::RUNNING_STATUSES, true))
                                <details><summary>{{ __('Vorlagenausschnitt anpassen') }}</summary>
                                    <form method="POST" action="{{ route('community.projects.template.update', $project) }}" class="community-form">
                                        @csrf @method('PATCH')
                                        @include('community.projects.crop', ['imageUrl' => route('community.projects.template', [$project, 'original' => 1]), 'cropX' => $project->crop_x, 'cropY' => $project->crop_y])
                                        <button class="primary-button">{{ __('Gemeinsame Vorlage speichern') }}</button>
                                    </form>
                                </details>
                            @endif
                        </section>
                    @endif
                    @if ($participant && $project->status === 'active' && $project->reserved_x !== null)
                        <section class="panel community-panel">
                            <h2>{{ __('Geschützter Projektbereich') }}</h2>
                            <p>{{ __('Deine Projektpixel erscheinen erst nach dem Abschluss durch die Projektleitung auf der Wall.') }}</p>
                            <p>{{ __('Projektpixel und Wall-Pixel nutzen denselben Konto-Vorrat. Alle :seconds Sekunden wächst ein Gratispixel nach, bis maximal :max.', ['seconds' => app(\App\Services\Wall\PixelQuota::class)->regenSeconds(), 'max' => app(\App\Services\Wall\PixelQuota::class)->maxFree()]) }}</p>
                            <div class="community-project-editor" data-pixels-url="{{ route('community.projects.pixels', $project) }}" data-paint-url="{{ route('community.projects.paint', $project) }}" data-quota-url="{{ route('api.pixels.status') }}" data-quota-template="{{ __('Pixelvorrat: :free von :max · Extra-Pixel: :credits') }}" data-quota-error="{{ __('Dein Pixelvorrat konnte nicht geladen werden. Der nächste Versuch erfolgt automatisch.') }}" data-width="{{ $project->width }}" data-height="{{ $project->height }}" data-editable="1">
                                <div class="community-canvas-zoom" data-community-zoom>
                                    @include('community.projects.canvas-zoom', ['zoomLabel' => __('Zoom der Malfläche'), 'pipette' => 'paint'])
                                    <div class="community-canvas-viewport" tabindex="0" aria-label="{{ __('Geschützter Projektbereich') }}">
                                        <canvas id="communityProjectCanvas" width="{{ $project->width }}" height="{{ $project->height }}" tabindex="0" aria-label="{{ __('Geschützter Projektbereich') }}"></canvas>
                                    </div>
                                </div>
                                <p data-paint-quota role="status"></p><p data-paint-coordinates role="status"></p>
                                <div class="color-bar community-project-color-bar" role="toolbar" aria-label="{{ __('Farbauswahl') }}">
                                    <x-palette-pipette data-paint-pick disabled />
                                    <div class="popup-color-row">
                                        @foreach (['#ff6b6b', '#ffd93d', '#48dbfb', '#1dd1a1', '#c56cf0', '#ff9ff3', '#2d3436', '#ffffff', '#000000', '#ff0000', '#00aa00', '#0044ff', '#ff8800', '#8b4513'] as $color)
                                            <button type="button" class="color-button community-color" data-color="{{ $color }}" style="background: {{ $color }}" aria-label="{{ __('Farbe :color', ['color' => $color]) }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"></button>
                                        @endforeach
                                    </div>
                                    <details class="game-color-settings" data-game-color-settings open>
                                        <summary aria-label="{{ __('Farbe und Maleinstellungen') }}"><span class="game-color-swatch" data-game-color-swatch style="background: #ff6b6b"></span></summary>
                                        <div class="game-color-settings-panel"><div class="color-picker-row">
                                            <input type="color" class="color-picker" data-paint-color value="#ff6b6b" aria-label="{{ __('Beliebige Farbe wählen') }}">
                                            <input type="text" class="color-hex-input" data-paint-hex value="#ff6b6b" maxlength="7" spellcheck="false" aria-label="{{ __('Hex-Farbcode') }}">
                                            <span class="active-color" data-paint-active>#ff6b6b</span>
                                        </div></div>
                                    </details>
                                    <button type="button" class="secondary-button project-paint-mode" data-paint-mode data-paint-label="{{ __('Malen') }}" data-pan-label="{{ __('Verschieben') }}" data-paint-help="{{ __('Malmodus aktiv: Berühren setzt Pixel und verbraucht deinen Vorrat.') }}" data-pan-help="{{ __('Ansicht verschieben: Wische über die Malfläche. Dabei werden keine Pixel gesetzt.') }}" aria-pressed="false" aria-describedby="communityProjectStatus">{{ __('Malen') }}</button>
                                    <output class="project-paint-balance" data-paint-balance aria-label="{{ __('Pixelvorrat') }}"></output>
                                </div>
                                <p id="communityProjectStatus" class="community-status" role="status" aria-live="polite"></p>
                            </div>
                            @if ($isProjectLeader)
                                <form method="POST" action="{{ route('community.projects.complete', $project) }}" class="community-form">
                                    @csrf
                                    <label><input type="checkbox" name="confirm_publish" value="1" required> {{ __('Ich möchte das Projekt abschließen und auf der Wall veröffentlichen.') }}</label>
                                    <button class="primary-button">{{ __('Abschließen und veröffentlichen') }}</button>
                                </form>
                            @endif
                        </section>
                    @endif
                    @if ($project->status === 'published' && $project->image_path)
                        <section class="panel community-panel">
                            <h2>{{ __('Veröffentlichtes Community-Projekt') }}</h2>
                            <img class="community-project-image" src="{{ route('community.projects.image', $project) }}" alt="{{ $project->title }}">
                            <p>{{ __('Die Fläche bleibt nach Veröffentlichung sieben Wochen reserviert.') }}</p>
                            <a class="primary-button" href="{{ route('home', ['x' => $project->reserved_x, 'y' => $project->reserved_y, 'z' => 4]) }}">{{ __('Projekt auf der Leinwand ansehen') }}</a>
                        </section>
                    @endif
                    @if ($participant)
                        <section class="panel community-panel community-chat" data-chat-url="{{ route('community.projects.chat', $project) }}" @if(in_array($project->status, \App\Models\CommunityProject::RUNNING_STATUSES, true)) data-send-url="{{ route('community.projects.chat.send', $project) }}" @endif>
                            <h2>{{ __('Projektchat') }}</h2>
                            <button class="secondary-button community-chat-older" type="button" hidden>{{ __('Ältere Nachrichten laden') }}</button>
                            <ol class="community-chat-messages" aria-live="polite"></ol>
                            @if(in_array($project->status, \App\Models\CommunityProject::RUNNING_STATUSES, true))
                                <form class="community-chat-form"><label>{{ __('Nachricht') }}<textarea name="message" rows="2" maxlength="1000" required></textarea></label><button class="primary-button">{{ __('Senden') }}</button></form>
                            @endif
                            <p class="community-chat-status" role="status" aria-live="polite"></p>
                        </section>
                    @endif
                @endif
            </main>
            @include('legal.partials.links')
        </div>
    </body>
</html>
