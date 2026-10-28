@extends('legal.layout')

@section('title', 'Nutzungsbedingungen')

@section('content')
    <h2>1. Geltungsbereich</h2>
    <p>
        Diese Bedingungen gelten für die Nutzung von Pixel Chaos, betrieben von
        <x-legal-value key="name" />. Die Nutzung der Wand ist kostenlos. Zusätzliche Leistungen
        (Pixel-Pakete, Wandvergrößerung) sind kostenpflichtig.
    </p>

    <h2>2. Spielregeln</h2>
    <ul>
        <li>Jeder Pixel kann von jedem überschrieben werden, auch deiner. Es gibt keinen Anspruch auf Bestand eines Pixels oder Bildes.</li>
        <li>Neu gesetzte Pixel sind kurz gesperrt (10 Minuten).</li>
        <li>Gäste erhalten einmalig 50 Pixel. Mit Konto wächst alle 30 Sekunden ein Pixel nach, maximal 50 im Vorrat.</li>
        <li>Verboten sind Hassbilder, verfassungsfeindliche oder verbotene Symbole, Beleidigungen, Pornografie, Gewaltdarstellungen und Inhalte, die Rechte Dritter verletzen.</li>
        <li>Automatisierte Zugriffe, Bots und Skripte zum Umgehen der Pixel-Limits sind nicht erlaubt.</li>
    </ul>

    <h2>3. Konto</h2>
    <p>
        Du musst deine Angaben wahrheitsgemäß machen und deine Zugangsdaten geheim halten. Pro Person
        ist ein Konto vorgesehen. Wir dürfen Konten sperren oder löschen, wenn du gegen diese Bedingungen verstößt.
        Du kannst dein Konto jederzeit löschen, siehe <a href="{{ route('legal.data-deletion') }}">Daten löschen</a>.
    </p>

    <h2>4. Inhalte und Rechte</h2>
    <p>
        Die Wand entsteht gemeinsam. Du räumst uns das einfache, unentgeltliche Recht ein, die von dir
        gesetzten Pixel im Rahmen des Dienstes darzustellen, zu speichern, zu vervielfältigen und in Bildern
        und Galerien der Wand zu zeigen. Pixel-Farben allein begründen in der Regel kein Urheberrecht an
        einzelnen Pixeln. Wir dürfen Inhalte entfernen, die gegen diese Bedingungen oder Gesetze verstoßen.
    </p>

    <h2>5. Kostenpflichtige Leistungen</h2>
    <p>
        Preise werden im Shop in Euro inklusive gesetzlicher Umsatzsteuer, soweit diese anfällt, angezeigt.
        Die Zahlung erfolgt über die dort angebotenen Zahlungsdienstleister. Gekaufte Pixel-Pakete werden
        dem Konto gutgeschrieben, eine Wandvergrößerung wird nach erfolgreicher Zahlung umgesetzt. Ein Kauf
        setzt ein Konto voraus.
    </p>

    <h2>6. Widerrufsrecht bei digitalen Inhalten</h2>
    <p>
        Verbraucher haben bei Fernabsatzverträgen grundsätzlich ein 14-tägiges Widerrufsrecht. Bei digitalen
        Inhalten, die nicht auf einem körperlichen Datenträger geliefert werden (hier: Gutschrift von Pixeln
        und Wandvergrößerung), erlischt das Widerrufsrecht nach § 356 Abs. 5 BGB, wenn wir mit der Ausführung
        begonnen haben, nachdem du ausdrücklich zugestimmt hast, dass wir vor Ablauf der Widerrufsfrist
        beginnen, und bestätigt hast, dass du dadurch dein Widerrufsrecht verlierst.
    </p>

    <h2>7. Verfügbarkeit und Haftung</h2>
    <p>
        Wir bemühen uns um einen unterbrechungsfreien Betrieb, schulden aber keine ständige Verfügbarkeit.
        Wir haften unbeschränkt bei Vorsatz und grober Fahrlässigkeit, bei Verletzung von Leben, Körper und
        Gesundheit sowie nach dem Produkthaftungsgesetz. Bei leichter Fahrlässigkeit haften wir nur bei
        Verletzung wesentlicher Vertragspflichten und begrenzt auf den vorhersehbaren, typischen Schaden.
    </p>

    <h2>8. Meldung von Verstößen</h2>
    <p>
        Rechtswidrige Inhalte kannst du an <x-legal-value key="email" /> melden. Bitte nenne die Position
        (x/y) der Wand und den Grund.
    </p>

    <h2>9. Schlussbestimmungen</h2>
    <p>
        Es gilt deutsches Recht unter Ausschluss des UN-Kaufrechts. Bei Verbrauchern bleiben zwingende
        Verbraucherschutzvorschriften des Aufenthaltsstaates unberührt. Sollten einzelne Regelungen
        unwirksam sein, bleibt der Rest wirksam.
    </p>
    <p>
        Rechtsverbindlich ist die deutsche Fassung dieser Bedingungen. Die englische und die spanische Fassung
        sind Übersetzungen und dienen nur der Information. Bei Auslegungsfragen geht die deutsche Fassung vor.
    </p>

    <p>Weitere Informationen: <a href="{{ route('legal.privacy') }}">Datenschutzerklärung</a>, <a href="{{ route('legal.impressum') }}">Impressum</a>.</p>
@endsection
