<nav class="lang-switch" aria-label="{{ __('Sprache wählen') }}">
    @foreach (config('locales.labels') as $code => $label)
        <a href="{{ route('language.switch', ['lang' => $code, 'to' => \App\Support\Locales::currentPath()]) }}"
           hreflang="{{ $code }}" lang="{{ $code }}" rel="nofollow" title="{{ $label }}"
           @if ($code === app()->getLocale()) class="active" aria-current="true" @endif>{{ strtoupper($code) }}</a>
    @endforeach
</nav>
