@props(['key'])
@php($value = config('legal.'.$key))
@if (filled($value))<span>{{ $value }}</span>@else<mark class="legal-missing" title="LEGAL_* in .env">[{{ $key }}: TODO]</mark>@endif
