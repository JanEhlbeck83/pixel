<footer class="legal-links">
    <a href="{{ route('legal.impressum') }}">{{ __('Impressum') }}</a>
    <a href="{{ route('legal.privacy') }}">{{ __('Datenschutz') }}</a>
    <a href="{{ route('legal.terms') }}">{{ __('Nutzungsbedingungen') }}</a>
    <a href="{{ route('legal.data-deletion') }}">{{ __('Daten löschen') }}</a>
    <x-language-switcher />
</footer>
