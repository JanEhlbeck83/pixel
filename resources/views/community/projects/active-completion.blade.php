<section class="panel community-panel community-proposal-completion" aria-label="{{ __('Projektabschluss und Veröffentlichung zustimmen') }}" data-active-completion>
    <div class="community-completion-action">
        <p class="community-completion-count" role="status">{{ __('Zustimmung zum Abschluss: :votes von :eligible Projektteilnehmenden.', ['votes' => $phaseVoteCount, 'eligible' => $phaseEligibleCount]) }}</p>
        @if ($hasAgreedToProjectClose)
            <p class="community-status" role="status">{{ __('Du hast dem Abschluss zugestimmt und bist jetzt in der Wartephase, bis die Gestaltung beendet ist. Bis zum Ende der Gestaltungszeit kannst du weiterhin am Projektbild mitarbeiten.') }}</p>
        @else
            <form method="POST" action="{{ route('community.projects.advance', $project) }}">
                @csrf
                <button class="secondary-button" type="submit">{{ __('Projektabschluss und Veröffentlichung zustimmen') }}</button>
            </form>
            <p>{{ __('Wenn das Projektbild für dich fertig ist, kannst du dem Abschluss der Gestaltungsphase und der Veröffentlichung zustimmen.') }}</p>
        @endif
        <p>{{ __('Die Zustimmung zum Abschluss ist verbindlich und kann nicht zurückgenommen werden. Bis zum Ende der Gestaltungszeit kannst du weiterhin am Projektbild mitarbeiten, sofern es noch nicht veröffentlicht wurde.') }}</p>
        <p>{{ __('Bei Einstimmigkeit wird das Projekt sofort veröffentlicht. In der Gestaltungsphase gibt es keine fünfminütige Nachfrist. Nach der Veröffentlichung sind keine Änderungen mehr möglich.') }}</p>
    </div>
</section>
