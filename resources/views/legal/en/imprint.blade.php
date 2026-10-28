@extends('legal.layout')

@section('title', 'Legal Notice')

@section('content')
    <h2>Information pursuant to Section 5 DDG</h2>
    <p>
        <x-legal-value key="name" /><br>
        <x-legal-value key="street" /><br>
        <x-legal-value key="zip_city" /><br>
        {{ config('legal.country') }}
    </p>

    <h2>Contact</h2>
    <p>
        Email: <x-legal-value key="email" /><br>
        Telephone: <x-legal-value key="phone" />
    </p>

    @if (filled(config('legal.register')))
        <h2>Commercial register entry</h2>
        <p>{{ config('legal.register') }}</p>
    @endif

    @if (filled(config('legal.vat_id')))
        <h2>VAT ID</h2>
        <p>VAT identification number pursuant to Section 27a UStG: {{ config('legal.vat_id') }}</p>
    @endif

    <h2>Responsible for content pursuant to Section 18(2) MStV</h2>
    <p>
        @if (filled(config('legal.responsible')))
            {{ config('legal.responsible') }}
        @else
            <x-legal-value key="name" />, address as above
        @endif
    </p>

    <h2>User content</h2>
    <p>
        The pixels on the wall are placed by users. We are responsible for such content only from the moment we
        become aware of a specific infringement. We remove unlawful content without undue delay once notified.
        Please report it by email to the address above.
    </p>

    <h2>Consumer dispute resolution</h2>
    <p>
        We are neither obliged nor willing to participate in dispute resolution proceedings before a consumer
        arbitration board. The European Commission provides a platform for online dispute resolution:
        <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener noreferrer">https://ec.europa.eu/consumers/odr</a>.
        We are under no obligation to participate and are not willing to do so.
    </p>

    <h2>Image credits</h2>
    <p>
        <em>Official Presidential Portrait of President Donald J. Trump (2025)</em>. Source: White House /
        Wikimedia Commons. Licence:
        <a href="https://commons.wikimedia.org/wiki/File:Official_Presidential_Portrait_of_President_Donald_J._Trump_(2025).jpg" target="_blank" rel="noopener noreferrer">Public domain</a>
        (work of the US federal government, Title 17, U.S. Code § 105).
    </p>
    <p>
        IP Geolocation by <a href="https://db-ip.com" target="_blank" rel="noopener noreferrer">DB-IP.com</a>
        (licence: <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener noreferrer">CC BY 4.0</a>).
    </p>

    <h2>Governing version</h2>
    <p>
        The German version of this legal notice is legally binding. The English and Spanish versions are
        translations provided for information only.
    </p>
@endsection

