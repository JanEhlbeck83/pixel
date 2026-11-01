<footer class="legal-links">
    <x-language-switcher />
    <nav class="legal-more-links">
        <a href="{{ route('faq') }}">{{ __('FAQ') }}</a>
        ·
        <a href="{{ route('legal.privacy') }}">{{ __('Datenschutz') }}</a>
        ·
        <a href="{{ route('legal.terms') }}">{{ __('Nutzungsbedingungen') }}</a>
        ·
        <a href="{{ route('legal.data-deletion') }}">{{ __('Daten löschen') }}</a>
        ·
        <a href="{{ route('legal.impressum') }}">{{ __('Impressum') }}</a>
    </nav>
</footer>
