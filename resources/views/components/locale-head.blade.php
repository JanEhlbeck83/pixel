@php($alternates = \App\Support\Locales::alternates())
@foreach ($alternates as $code => $href)
    <link rel="alternate" hreflang="{{ $code }}" href="{{ $href }}">
@endforeach
@if ($alternates)
    <link rel="canonical" href="{{ $alternates[app()->getLocale()] }}">
    <meta property="og:locale" content="{{ app()->getLocale() }}">
@endif
