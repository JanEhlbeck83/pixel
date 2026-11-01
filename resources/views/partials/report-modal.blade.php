<div id="reportBar" class="report-bar hidden" role="status">
    <span id="reportBarText">{{ __('Ziehe ein Rechteck über den Bereich, den du melden möchtest.') }}</span>
    <button type="button" class="secondary-button" id="reportCancelBar">{{ __('Abbrechen') }}</button>
</div>

<div id="reportModal" class="shop-modal hidden" role="dialog" aria-modal="true" aria-labelledby="reportTitle">
    <div class="shop-card report-card">
        <h3 id="reportTitle">{{ __('Inhalt melden') }}</h3>
        <p class="report-area" id="reportArea"></p>
        <form id="reportForm" class="feedback-form">
            @csrf
            <label for="reportReason">{{ __('Grund') }}</label>
            <select id="reportReason" name="reason" required>
                @foreach (\App\Models\Report::REASON_LABELS as $value => $label)
                    <option value="{{ $value }}">{{ __($label) }}</option>
                @endforeach
            </select>
            <label for="reportMessage">{{ __('Anmerkung (optional)') }}</label>
            <textarea id="reportMessage" name="message" rows="3" maxlength="1000"></textarea>
            @guest
                <label for="reportEmail">{{ __('Deine E-Mail-Adresse (optional, für Rückfragen)') }}</label>
                <input id="reportEmail" name="email" type="email" maxlength="255" autocomplete="email">
            @endguest
            <div class="report-modal-actions">
                <button type="submit" class="primary-button" id="reportSubmitBtn">{{ __('Meldung senden') }}</button>
                <button type="button" class="secondary-button" id="reportCancelBtn">{{ __('Abbrechen') }}</button>
            </div>
            <p id="reportStatus" class="feedback-status" role="status" aria-live="polite"></p>
        </form>
    </div>
</div>
