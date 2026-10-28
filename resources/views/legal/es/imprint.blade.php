@extends('legal.layout')

@section('title', 'Aviso legal')

@section('content')
    <h2>Datos conforme al art. 5 DDG</h2>
    <p>
        <x-legal-value key="name" /><br>
        <x-legal-value key="street" /><br>
        <x-legal-value key="zip_city" /><br>
        {{ config('legal.country') }}
    </p>

    <h2>Contacto</h2>
    <p>
        Correo electrónico: <x-legal-value key="email" /><br>
        Teléfono: <x-legal-value key="phone" />
    </p>

    @if (filled(config('legal.register')))
        <h2>Inscripción en el registro</h2>
        <p>{{ config('legal.register') }}</p>
    @endif

    @if (filled(config('legal.vat_id')))
        <h2>Número de IVA</h2>
        <p>Número de identificación a efectos del IVA conforme al art. 27a UStG: {{ config('legal.vat_id') }}</p>
    @endif

    <h2>Responsable del contenido conforme al art. 18, apdo. 2 MStV</h2>
    <p>
        @if (filled(config('legal.responsible')))
            {{ config('legal.responsible') }}
        @else
            <x-legal-value key="name" />, dirección como arriba
        @endif
    </p>

    <h2>Contenido de los usuarios</h2>
    <p>
        Los píxeles del muro los colocan los usuarios. Solo somos responsables de dichos contenidos a partir del
        momento en que tenemos conocimiento de una infracción concreta. Retiramos sin demora los contenidos
        ilícitos tras ser notificados. Comunícalos por correo electrónico a la dirección indicada arriba.
    </p>

    <h2>Resolución de litigios en materia de consumo</h2>
    <p>
        No estamos obligados ni dispuestos a participar en procedimientos de resolución de litigios ante una
        entidad de arbitraje de consumo. La Comisión Europea ofrece una plataforma de resolución de litigios en
        línea:
        <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener noreferrer">https://ec.europa.eu/consumers/odr</a>.
        No existe obligación ni disposición por nuestra parte de participar.
    </p>

    <h2>Créditos de imagen</h2>
    <p>
        <em>Official Presidential Portrait of President Donald J. Trump (2025)</em>. Fuente: White House /
        Wikimedia Commons. Licencia:
        <a href="https://commons.wikimedia.org/wiki/File:Official_Presidential_Portrait_of_President_Donald_J._Trump_(2025).jpg" target="_blank" rel="noopener noreferrer">dominio público</a>
        (obra del Gobierno federal de EE. UU., Título 17, U.S. Code § 105).
    </p>
    <p>
        IP Geolocation by <a href="https://db-ip.com" target="_blank" rel="noopener noreferrer">DB-IP.com</a>
        (licencia: <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener noreferrer">CC BY 4.0</a>).
    </p>

    <h2>Versión determinante</h2>
    <p>
        La versión alemana de este aviso legal es la jurídicamente vinculante. Las versiones en inglés y en
        español son traducciones que se ofrecen únicamente a título informativo.
    </p>
@endsection

