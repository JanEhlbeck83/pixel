<div class="community-canvas-tools">
    <button type="button" class="secondary-button community-pipette" @if ($pipette === 'reference') data-reference-pick @else data-paint-pick @endif aria-pressed="{{ $pipette === 'reference' ? 'true' : 'false' }}" aria-label="{{ $pipette === 'reference' ? __('Pipette: Farbe und Position übernehmen') : __('Pipette: Farbe aus der Malfläche übernehmen') }}" title="{{ $pipette === 'reference' ? __('Pipette: Farbe und Position übernehmen') : __('Pipette: Farbe aus der Malfläche übernehmen') }}" disabled>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6 3-3a2.1 2.1 0 0 1 3 3l-3 3M13 5l6 6M14 8 4 18l-1 3 3-1L16 10M5 17l2 2"/></svg>
    </button>
    @if ($pipette === 'paint')
        <span class="community-picked-pixel" data-paint-selection data-reference-label="{{ __('Gewählte Koordinaten und Farbe aus Vorlage') }}" data-canvas-label="{{ __('Gewählte Koordinaten und Farbe aus Malfläche') }}" role="status" hidden></span>
    @endif
    <div class="community-canvas-zoom-controls" role="group" aria-label="{{ $zoomLabel }}">
    <span class="community-canvas-zoom-label">{{ $zoomLabel }}</span>
    <button type="button" class="secondary-button" data-zoom-out aria-label="{{ __('Verkleinern') }}"><span class="zoom-sign">−</span></button>
    <button type="button" class="secondary-button" data-zoom-reset data-label="{{ __('Zoom auf 100 Prozent zurücksetzen; aktuell :percent Prozent') }}" aria-label="{{ __('Zoom auf 100 Prozent zurücksetzen; aktuell :percent Prozent', ['percent' => 1600]) }}">1600%</button>
    <button type="button" class="secondary-button" data-zoom-in aria-label="{{ __('Vergrößern') }}" disabled><span class="zoom-sign">+</span></button>
    </div>
</div>
