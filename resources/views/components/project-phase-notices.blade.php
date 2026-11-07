@if (auth()->user()?->hasVerifiedEmail() && ! auth()->user()->isBanned())
    @php
    $phaseNoticeConfig = [
        'url' => route('api.community.phase-updates'),
        'userId' => auth()->id(),
        'locale' => app()->getLocale(),
        'heading' => __('Neues aus deinen Gruppenprojekten'),
        'projectLink' => __('Projekt ansehen'),
        'groupLink' => __('Zur Gruppe'),
        'joinLink' => __('Jetzt am Projekt teilnehmen'),
        'dismiss' => __('Schließen'),
        'error' => __('Projektmeldungen konnten nicht aktualisiert werden. Der nächste Versuch erfolgt automatisch.'),
    ];
    @endphp
    <script type="application/json" id="projectPhaseNoticeConfig">@json($phaseNoticeConfig)</script>
    @vite('resources/js/project-phase-notices.js')
@endif
