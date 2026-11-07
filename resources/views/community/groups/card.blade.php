<article class="community-card">
    <div class="community-card-heading">
        @if ($group->emblem)
            <img class="community-group-emblem" src="{{ asset('images/community/emblems/'.$group->emblem.'.svg') }}" alt="{{ __('Emblem der Gruppe :name', ['name' => $group->name]) }}" width="72" height="72" loading="lazy">
        @endif
        <div>
            <h3><a href="{{ route('community.groups.show', $group) }}">{{ $group->name }}</a></h3>
        </div>
    </div>
    <p>{{ $group->description }}</p>
    <small>{{ __(':count Mitglieder', ['count' => $group->members_count]) }}</small>
    <div class="community-group-counters">
        <span>{{ __('Aktive Projekte') }} <strong>{{ $group->active_projects_count }}</strong></span>
        <span>{{ __('Abgeschlossene Projekte') }} <strong>{{ $group->completed_projects_count }}</strong></span>
    </div>
    <a class="community-gallery-link" href="{{ route('community.groups.gallery', $group) }}">{{ __('Abgeschlossene Projekte ansehen') }}</a>
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
</article>
