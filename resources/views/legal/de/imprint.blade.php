@extends('legal.layout')

@section('title', 'Impressum')

@section('content')
    <h2>Angaben gemäß § 5 DDG</h2>
    <p>
        <x-legal-value key="name" /><br>
        <x-legal-value key="street" /><br>
        <x-legal-value key="zip_city" /><br>
        {{ config('legal.country') }}
    </p>

    <h2>Kontakt</h2>
    <p>
        E-Mail: <x-legal-value key="email" /><br>
        Telefon: <x-legal-value key="phone" />
    </p>

    @if (filled(config('legal.register')))
        <h2>Registereintrag</h2>
        <p>{{ config('legal.register') }}</p>
    @endif

    @if (filled(config('legal.vat_id')))
        <h2>Umsatzsteuer-ID</h2>
        <p>Umsatzsteuer-Identifikationsnummer nach § 27a UStG: {{ config('legal.vat_id') }}</p>
    @endif

    <h2>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h2>
    <p>
        @if (filled(config('legal.responsible')))
            {{ config('legal.responsible') }}
        @else
            <x-legal-value key="name" />, Anschrift wie oben
        @endif
    </p>

    <h2>Nutzerinhalte</h2>
    <p>
        Die Pixel auf der Wand werden von Nutzerinnen und Nutzern gesetzt. Für diese Inhalte sind wir erst ab
        Kenntnis einer konkreten Rechtsverletzung verantwortlich. Rechtswidrige Inhalte entfernen wir nach
        Hinweis unverzüglich. Bitte melde sie per E-Mail an die oben genannte Adresse.
    </p>

    <h2>Verbraucherstreitbeilegung</h2>
    <p>
        Wir sind weder verpflichtet noch bereit, an Streitbeilegungsverfahren vor einer
        Verbraucherschlichtungsstelle teilzunehmen. Die EU-Kommission stellt eine Plattform zur
        Online-Streitbeilegung bereit:
        <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener noreferrer">https://ec.europa.eu/consumers/odr</a>.
        Eine Pflicht oder Bereitschaft zur Teilnahme besteht nicht.
    </p>

    <h2>Bildnachweis</h2>
    <p>
        <em>Official Presidential Portrait of President Donald J. Trump (2025)</em>. Quelle: White House /
        Wikimedia Commons. Lizenz:
        <a href="https://commons.wikimedia.org/wiki/File:Official_Presidential_Portrait_of_President_Donald_J._Trump_(2025).jpg" target="_blank" rel="noopener noreferrer">Public domain</a>
        (Werk der US-Bundesregierung, Titel 17, U.S. Code § 105).
    </p>
            <p>
                IP Geolocation by <a href="https://db-ip.com" target="_blank" rel="noopener noreferrer">DB-IP.com</a>
                (Lizenz: <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener noreferrer">CC BY 4.0</a>).
            </p>

            <h2>Maßgebliche Fassung</h2>
            <p>
                Rechtsverbindlich ist die deutsche Fassung dieses Impressums. Die englische und die spanische Fassung
                sind Übersetzungen und dienen nur der Information.
            </p>
@endsection
