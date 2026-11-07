<section id="wallDraftPanel" class="wall-draft-panel" aria-label="{{ __('Mein Pixelbild') }}" inert>
    <div class="wall-draft-heading">
        <strong>{{ __('Mein Pixelbild') }} · 20 × 50 px</strong>
        <button type="button" class="secondary-button" data-draft-close aria-label="{{ __('Entwurf einklappen') }}">×</button>
    </div>
    <details class="wall-draft-details">
        <summary>{{ __('1 Pixel je neuem Feld · Infos') }}</summary>
        <p>{{ __('Das erste Bemalen eines Feldes kostet sofort einen Pixel. Umfärben und Radieren sind danach kostenlos. Keine Erstattung; das Platzieren kostet nichts zusätzlich.') }}</p>
    @guest
        <p class="wall-draft-guest">
            {{ __('Nur vorübergehend: Ohne Konto geht der Entwurf nach dem Schließen des Browsers verloren.') }}
            <a href="{{ route('register') }}">{{ __('Registrieren: Entwurf behalten und mit bis zu 50 Pixeln weiterzeichnen') }}</a>
            · <a href="{{ route('login') }}">{{ __('Einloggen und Entwurf übernehmen') }}</a>
        </p>
    @else
        <a href="{{ route('artworks.index', auth()->user()) }}">{{ __('Meine Pixelgalerie') }}</a>
    @endguest
    <label class="wall-draft-select hidden" data-draft-selector>
        {{ __('Gespeicherte Entwürfe') }} <select data-draft-select></select>
    </label>
    <label class="wall-draft-title">{{ __('Bildtitel') }} <input type="text" maxlength="80" data-draft-title autocomplete="off"></label>
    </details>
    @guest
        <a class="wall-draft-note" href="{{ route('register') }}">{{ __('Entwurf behalten? Registrieren & bis zu 50 Pixel erhalten') }}</a>
    @endguest
    <div class="wall-draft-tools">
        <button type="button" class="secondary-button" data-draft-erase aria-pressed="false">{{ __('Radierer') }}</button>
        <button type="button" class="secondary-button" data-draft-zoom-out aria-label="{{ __('Verkleinern') }}">−</button>
        <label><span class="sr-only">{{ __('Zoom') }}</span>
            <select data-draft-zoom>
                @foreach ([2, 4, 6, 8, 10, 12, 16, 20] as $zoom)
                    <option value="{{ $zoom }}">{{ $zoom * 100 }}%</option>
                @endforeach
            </select>
        </label>
        <button type="button" class="secondary-button" data-draft-zoom-in aria-label="{{ __('Vergrößern') }}">+</button>
        <button type="button" class="secondary-button" data-draft-fit>{{ __('Einpassen') }}</button>
    </div>
    <div class="wall-draft-viewport" data-draft-viewport>
        <canvas width="20" height="50" data-draft-canvas tabindex="0" aria-label="{{ __('Entwurf: 20 Pixel breit, 50 Pixel hoch. Pfeiltasten bewegen, Leertaste malt oder radiert.') }}"></canvas>
    </div>
    <p data-draft-status role="status" aria-live="polite">{{ __('Entwurf wird geladen …') }}</p>
    <div class="wall-draft-actions">
        <button type="button" class="secondary-button" data-draft-save>{{ __('Speichern') }}</button>
        <button type="button" class="primary-button" data-draft-place disabled>{{ __('Auf der Wall platzieren') }}</button>
    </div>
</section>
<section id="wallDraftPlacement" class="wall-draft-placement hidden" aria-label="{{ __('Bild platzieren') }}">
    <strong>{{ __('Freie Position auf der Wall wählen') }}</strong>
    <p data-draft-placement-status role="status"></p>
    <div class="wall-draft-actions">
        <button type="button" class="primary-button" data-draft-confirm disabled>{{ __('Hier veröffentlichen') }}</button>
        <button type="button" class="secondary-button" data-draft-back>{{ __('Zurück zum Entwurf') }}</button>
    </div>
</section>
