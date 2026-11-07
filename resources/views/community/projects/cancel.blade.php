@if ($isProjectLeader && in_array($project->status, \App\Models\CommunityProject::RUNNING_STATUSES, true))
    <details class="community-create">
        <summary class="secondary-button">{{ __('Projekt vorzeitig abbrechen') }}</summary>
        <form method="POST" action="{{ route('community.projects.cancel', $project) }}" class="community-form">
            @csrf
            <p>{{ __('Der Abbruch ist endgültig. Der Projektplatz und eine reservierte Fläche werden frei. Entwürfe und Projektpixel bleiben gespeichert, werden aber nicht veröffentlicht.') }}</p>
            <label><input type="checkbox" name="confirm_cancel" value="1" required> {{ __('Ich möchte dieses Projekt endgültig abbrechen.') }}</label>
            <button class="secondary-button" type="submit">{{ __('Projekt endgültig abbrechen') }}</button>
        </form>
    </details>
@endif
