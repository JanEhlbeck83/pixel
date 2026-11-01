@php
    $flags = [
        'en' => '<svg viewBox="0 0 60 40" aria-hidden="true"><rect width="60" height="40" fill="#012169"/><path d="M0 0l60 40M60 0L0 40" stroke="#fff" stroke-width="8"/><path d="M0 0l60 40M60 0L0 40" stroke="#C8102E" stroke-width="3"/><path d="M30 0v40M0 20h60" stroke="#fff" stroke-width="13"/><path d="M30 0v40M0 20h60" stroke="#C8102E" stroke-width="8"/></svg>',
        'de' => '<svg viewBox="0 0 60 40" aria-hidden="true"><rect width="60" height="40" fill="#FFCE00"/><rect width="60" height="26.7" fill="#DD0000"/><rect width="60" height="13.3" fill="#000"/></svg>',
        'es' => '<svg viewBox="0 0 60 40" aria-hidden="true"><rect width="60" height="40" fill="#AA151B"/><rect y="10" width="60" height="20" fill="#F1BF00"/></svg>',
    ];
    $current = app()->getLocale();
    $labels = config('locales.labels');
@endphp
<details {{ $attributes->merge(['class' => 'lang-switch']) }} ontoggle="if (this.open && this.classList.contains('lang-switch--down')) this.scrollIntoView({block: 'nearest'})">
    <summary aria-label="{{ __('Sprache wählen') }}" title="{{ __('Sprache wählen') }}">
        <span class="lang-flag">{!! $flags[$current] ?? '' !!}</span>
        <span class="lang-name">{{ $labels[$current] ?? strtoupper($current) }}</span>
    </summary>
    <ul class="lang-menu">
        @foreach ($labels as $code => $label)
            <li>
                <a href="{{ route('language.switch', ['lang' => $code, 'to' => \App\Support\Locales::currentPath()]) }}"
                   hreflang="{{ $code }}" lang="{{ $code }}" rel="nofollow"
                   @if ($code === $current) class="active" aria-current="true" @endif>
                    <span class="lang-flag">{!! $flags[$code] ?? '' !!}</span>
                    <span class="lang-name">{{ $label }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</details>
