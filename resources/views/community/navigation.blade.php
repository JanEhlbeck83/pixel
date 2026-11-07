<nav class="community-navigation" aria-label="{{ __('Community') }}">
    @if ($showGroupLink ?? true)
        <a class="secondary-button" href="{{ route('community.groups.show', $group) }}">{{ __('Zur Gruppe :name', ['name' => $group->name]) }}</a>
    @endif
    <a class="secondary-button" href="{{ route('community.index') }}">{{ __('Gruppen entdecken') }}</a>
</nav>
