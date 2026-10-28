@extends('legal.layout')

@section('title', 'Daten löschen')

@section('content')
    <p>
        Du kannst dein Konto und die damit verbundenen personenbezogenen Daten jederzeit löschen lassen.
        Das gilt auch, wenn du dich über <strong>Facebook</strong> oder Google angemeldet hast.
    </p>

    <h2>Option 1: Selbst löschen (sofort)</h2>
    <ol>
        <li><a href="{{ route('login') }}">Melde dich an</a> (auch mit Facebook).</li>
        <li>Öffne dein <a href="{{ route('profile.edit') }}">Profil</a>.</li>
        <li>Wähle „Konto löschen“ und bestätige.</li>
    </ol>
    <p>
        Hinweis: Wenn du dich nur mit Facebook oder Google angemeldet hast, kennst du ggf. kein Passwort.
        Nutze dann <a href="{{ route('password.request') }}">Passwort vergessen</a> mit deiner Konto-E-Mail,
        um eines zu setzen, oder wähle Option 2.
    </p>

    <h2>Option 2: Per E-Mail beantragen</h2>
    <p>
        Schreib an <x-legal-value key="email" /> mit dem Betreff „Datenlöschung“. Nenne die E-Mail-Adresse
        deines Kontos (bei Facebook-Login die E-Mail-Adresse deines Facebook-Kontos). Wir bearbeiten den
        Antrag innerhalb von 30 Tagen und bestätigen dir die Löschung.
    </p>

    <h2>Was wird gelöscht?</h2>
    <ul>
        <li>Konto mit Name, E-Mail-Adresse, Passwort-Hash und der Facebook- bzw. Google-ID</li>
        <li>Zuordnung deiner Pixel und Aktivitäten zu deinem Konto (die Pixel bleiben anonym auf der Wand)</li>
        <li>Anmelde-Sitzungen und Zugangsdaten</li>
    </ul>
    <p>
        Bestellungen bleiben als Buchungsbeleg ohne Konto-Bezug erhalten, soweit wir sie aus steuer- und
        handelsrechtlichen Gründen aufbewahren müssen.
    </p>

    <h2>Verknüpfung bei Facebook entfernen</h2>
    <ol>
        <li>Öffne bei Facebook <em>Einstellungen &amp; Privatsphäre → Einstellungen</em>.</li>
        <li>Wähle <em>Apps und Websites</em> (bzw. „Deine Aktivitäten außerhalb von Facebook“).</li>
        <li>Wähle „Pixel Chaos“ und klicke auf <em>Entfernen</em>.</li>
    </ol>
    <p>
        Damit kann Pixel Chaos keine weiteren Daten mehr von Facebook erhalten. Bereits gespeicherte Daten
        entfernst du wie oben beschrieben. Weitere Informationen: <a href="{{ route('legal.privacy') }}">Datenschutzerklärung</a>.
    </p>
@endsection
