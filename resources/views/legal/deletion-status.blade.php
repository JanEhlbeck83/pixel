@extends('legal.layout')

@section('title', __('Löschung bestätigt'))

@section('content')
    <p>{{ __('Deine Anfrage zur Datenlöschung wurde bearbeitet. Konto und personenbezogene Daten sind bei PixRiot gelöscht.') }}</p>
    <p>{{ __('Bestätigungscode') }}: <code>{{ $code }}</code></p>
@endsection
