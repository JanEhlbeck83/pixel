<p>{{ __('Projektformat: :width × :height Pixel', ['width' => $project->width, 'height' => $project->height]) }}</p>
<p>{{ __('Angemeldet: :count · Benötigt: :minimum einschließlich Projektleitung', ['count' => $project->participantCount(), 'minimum' => $project->minimum_participants]) }}</p>
<p>{{ __('Erwartete Arbeitsdauer: :hours Stunden (Orientierung)', ['hours' => $project->expected_duration_hours]) }}</p>
@if ($project->status === 'recruiting')
    <p>{{ __('Innerhalb von 24 Stunden muss die Mindestbesetzung erreicht sein.') }}</p>
    <p>{{ $project->scheduled_start_at ? __('Geplanter Start: :date', ['date' => $project->scheduled_start_at->format('d.m.Y H:i')]) : __('Start sofort, sobald genug Mitglieder angemeldet sind.') }}</p>
@elseif ($project->status === 'scheduled')
    <p>{{ __('Geplanter Start: :date', ['date' => $project->scheduled_start_at->format('d.m.Y H:i')]) }}</p>
@elseif ($project->started_at)
    <p>{{ __('Gestartet am :date', ['date' => $project->started_at->format('d.m.Y H:i')]) }}</p>
    @if ($project->status === 'active')
        <p>{{ __('Abschluss durch die Projektleitung spätestens am :date; danach verfällt das Projekt.', ['date' => $project->project_close_at->format('d.m.Y H:i')]) }}</p>
    @endif
@endif
@php($deadline = match($project->status) {
    'recruiting' => $project->recruitment_close_at,
    'scheduled' => $project->scheduled_start_at,
    'active' => $project->project_close_at,
    default => null,
})
@if ($deadline)
    <p class="community-phase-countdown" role="timer" aria-live="off" data-countdown-reload data-countdown-now="{{ now()->toIso8601String() }}" data-countdown-until="{{ $deadline->toIso8601String() }}" data-countdown-template="{{ __('Noch :days Tage, :hours Stunden, :minutes Minuten und :seconds Sekunden.') }}" data-countdown-expired="{{ __('Die Frist ist erreicht. Lade die Seite neu, um den aktuellen Phasenstatus zu sehen.') }}"></p>
@endif
@if (in_array($project->status, ['cancelled', 'archived'], true))
    <p>{{ __(\App\Models\CommunityProject::CANCELLATION_MESSAGES[$project->cancellation_reason] ?? 'Dieses Projekt wurde abgebrochen.') }}</p>
@endif
