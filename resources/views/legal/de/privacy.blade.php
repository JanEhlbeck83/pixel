@extends('legal.layout')

@section('title', 'Datenschutzerklärung')

@section('content')
    <h2>1. Verantwortlicher</h2>
    <p>
        <x-legal-value key="name" />, <x-legal-value key="street" />, <x-legal-value key="zip_city" />,
        {{ config('legal.country') }}<br>
        E-Mail: <x-legal-value key="email" />
    </p>

    <h2>2. Überblick</h2>
    <p>
        Pixel Chaos ist eine gemeinsame Pixelwand. Wir verarbeiten personenbezogene Daten nur, soweit das für
        Betrieb, Konto, Zahlungen und Sicherheit nötig ist. Rechtsgrundlage ist die DSGVO.
    </p>

    <h2>3. Hosting und Server-Logdaten</h2>
    <p>
        Beim Aufruf der Seite verarbeitet der Server technisch notwendige Daten (IP-Adresse, Datum und Uhrzeit,
        aufgerufene Adresse, Browser-Angaben). Das dient dem sicheren und stabilen Betrieb
        (Art. 6 Abs. 1 lit. f DSGVO). Hosting: <x-legal-value key="hosting" />.
    </p>

    <h2>4. Cookies und lokaler Speicher</h2>
    <p>
        Wir setzen nur technisch notwendige Cookies: ein Sitzungs-Cookie, ein Schutz-Cookie gegen
        Cross-Site-Angriffe (CSRF/XSRF) und, wenn du „angemeldet bleiben“ nutzt, ein Anmelde-Cookie. Nach einer bewussten Sprachwahl kommt das funktionale Cookie „pixel_locale“ hinzu
        (siehe Abschnitt 15). Zusätzlich
        merkt sich dein Browser im lokalen Speicher, dass du den Hinweis zu Cookies und die Spielregeln
        bestätigt hast. Diese Angaben verlassen deinen Browser nicht. Rechtsgrundlage ist § 25 Abs. 2 Nr. 2
        TDDDG und Art. 6 Abs. 1 lit. f DSGVO. Tracking- oder Werbe-Cookies setzen wir nicht ein.
    </p>

    <h2>5. Spielen als Gast</h2>
    <p>
        Gäste dürfen einmalig 50 Pixel setzen. Dafür speichern wir einen Hash (SHA-256) deiner IP-Adresse
        zusammen mit der Zahl der gesetzten Pixel. Die IP-Adresse selbst wird dafür nicht gespeichert
        (Art. 6 Abs. 1 lit. f DSGVO, Schutz vor Missbrauch).
    </p>

    <h2>6. Konto und Anmeldung</h2>
    <p>
        Bei der Registrierung speichern wir Name, E-Mail-Adresse und ein verschlüsselt gespeichertes Passwort
        (Hash), außerdem Zeitpunkte deiner Aktivität sowie deinen Pixel-Vorrat. Wir senden dir
        E-Mails zur Bestätigung deiner Adresse und zum Zurücksetzen deines Passworts
        (Art. 6 Abs. 1 lit. b DSGVO).
    </p>

    <h2>7. Anmeldung mit Facebook oder Google</h2>
    <p>
        Du kannst dich mit Facebook oder Google anmelden. Das ist freiwillig. Beim ersten Login legen wir
        automatisch ein Konto an.
    </p>
    <h3>Facebook</h3>
    <p>
        Anbieter von Facebook Login ist Meta Platforms Ireland Limited, Merrion Road, Dublin 4, D04 X2K5,
        Irland. Wenn du „Facebook“ wählst, leitet dich dein Browser zu Facebook weiter. Dabei erhält Meta
        technische Daten (u. a. deine IP-Adresse) und die Information, dass du dich bei Pixel Chaos anmelden
        willst. Nach deiner Zustimmung übermittelt Facebook uns deine <strong>Facebook-ID</strong>, deinen
        <strong>Namen</strong> und deine <strong>E-Mail-Adresse</strong>. Wir speichern diese Angaben
        (ID, Name, E-Mail), um dein Konto zu führen. Wir veröffentlichen nichts auf Facebook und lesen keine
        Freundeslisten, Beiträge oder anderen Inhalte. Rechtsgrundlage ist deine Einwilligung
        (Art. 6 Abs. 1 lit. a DSGVO) und die Vertragserfüllung (lit. b). Mehr dazu in der
        <a href="https://www.facebook.com/privacy/policy/" target="_blank" rel="noopener noreferrer">Datenrichtlinie von Meta</a>.
        Du kannst die Verknüpfung jederzeit in deinen Facebook-Einstellungen unter „Apps und Websites“
        entfernen. Siehe auch <a href="{{ route('legal.data-deletion') }}">Anleitung zur Datenlöschung</a>.
    </p>
    <h3>Google</h3>
    <p>
        Anbieter ist Google Ireland Limited, Gordon House, Barrow Street, Dublin 4, Irland. Wir erhalten
        Google-ID, Namen und E-Mail-Adresse. Rechtsgrundlage ist Art. 6 Abs. 1 lit. a und b DSGVO.
        Datenschutzhinweise:
        <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">policies.google.com/privacy</a>.
    </p>
    <h3>Teilen auf Facebook</h3>
    <p>
        Der Button „Auf Facebook teilen“ ist ein einfacher Link. Erst wenn du ihn anklickst, wird
        Facebook geöffnet und es werden Daten an Meta übertragen. Beim bloßen Aufruf unserer Seite findet
        keine Übertragung an Facebook statt.
    </p>
    <p>
        Meta und Google können Daten auch in Drittländern (z. B. USA) verarbeiten. Beide Unternehmen
        sind nach dem EU-US Data Privacy Framework zertifiziert.
    </p>

    <h2>8. Pixel, Aktivitätsverlauf und Galerie</h2>
    <p>
        Wenn du einen Pixel setzt, speichern wir Position, Farbe, deinen Nutzer-Bezug und den Zeitpunkt. Ein
        Aktivitätsverlauf („wer hat was getan“) und Bilder (Schnappschüsse) der Wand zeigen das Ergebnis
        öffentlich. Dein Name wird dabei, soweit er angezeigt wird, öffentlich sichtbar. Rechtsgrundlage ist
        Art. 6 Abs. 1 lit. b DSGVO (Nutzung der Wand). Wenn du dein Konto löschst, wird der Bezug zu dir
        aus Pixeln und Aktivitäten gelöst. Die Pixel selbst bleiben als anonymes Bild der Wand bestehen.
    </p>

    <h2>9. Feedback</h2>
    <p>
        Im Feedback-Formular speichern wir deine Nachricht, deine optionale E-Mail-Adresse und, wenn du
        angemeldet bist, dein Konto. Wir nutzen das nur zur Bearbeitung deiner Nachricht
        (Art. 6 Abs. 1 lit. f DSGVO).
    </p>

    <h2>10. Käufe und Zahlungen</h2>
    <p>
        Du kannst Pixel-Pakete und Wandvergrößerungen kaufen. Wir speichern Bestellnummer, Produkt, Betrag,
        Zahlungsanbieter, Zahlungsreferenz, Status und Zeitpunkte. Die Zahlung wickelt einer der folgenden
        Dienstleister ab, je nachdem, was du wählst:
    </p>
    <ul>
        <li>Stripe Payments Europe Ltd., Dublin, Irland (<a href="https://stripe.com/de/privacy" target="_blank" rel="noopener noreferrer">Datenschutz</a>)</li>
        <li>PayPal (Europe) S.à r.l. et Cie, S.C.A., Luxemburg (<a href="https://www.paypal.com/de/legalhub/privacy-full" target="_blank" rel="noopener noreferrer">Datenschutz</a>)</li>
        <li>Mollie B.V., Amsterdam, Niederlande (<a href="https://www.mollie.com/de/privacy" target="_blank" rel="noopener noreferrer">Datenschutz</a>)</li>
    </ul>
    <p>
        Deine Zahlungsdaten (z. B. Kartennummer) geben wir nicht selbst ein oder speichern sie, sie gehen
        direkt an den Anbieter. Rechtsgrundlage ist Art. 6 Abs. 1 lit. b DSGVO. Bestelldaten bewahren wir
        wegen steuer- und handelsrechtlicher Pflichten bis zu zehn Jahre auf (Art. 6 Abs. 1 lit. c DSGVO).
        Beim Löschen deines Kontos wird die Bestellung vom Konto getrennt, bleibt aber als Buchungsbeleg erhalten.
    </p>

    <h2>11. Schriftarten</h2>
    <p>
        Auf den Anmelde- und Konto-Seiten laden wir Schriftarten vom Anbieter Bunny Fonts
        (BunnyWay d.o.o., Slowenien). Dabei wird deine IP-Adresse an Bunny übertragen. Bunny ist ein
        datenschutzfreundlicher Dienst in der EU, der keine Logs zu Werbezwecken führt
        (Art. 6 Abs. 1 lit. f DSGVO).
    </p>

    <h2>12. Speicherdauer</h2>
    <p>
        Wir löschen Daten, sobald der Zweck entfällt. Kontodaten löschen wir, wenn du dein Konto löschst.
        Gesetzliche Aufbewahrungspflichten bleiben unberührt.
    </p>

    <h2>13. Deine Rechte</h2>
    <p>
        Du hast das Recht auf Auskunft (Art. 15), Berichtigung (Art. 16), Löschung (Art. 17), Einschränkung
        (Art. 18), Datenübertragbarkeit (Art. 20) und Widerspruch (Art. 21 DSGVO). Eine erteilte Einwilligung
        kannst du jederzeit mit Wirkung für die Zukunft widerrufen. Schreib uns dazu an
        <x-legal-value key="email" />. Du kannst dich außerdem bei einer Datenschutz-Aufsichtsbehörde
        beschweren, z. B. bei der Behörde in deinem Bundesland oder an deinem gewöhnlichen Aufenthaltsort.
        Wenn du in Spanien lebst, kannst du dich an die spanische Datenschutzbehörde wenden, die
        <a href="https://www.aepd.es" target="_blank" rel="noopener noreferrer">Agencia Española de Protección de Datos (AEPD)</a>.
    </p>

    <h2>14. Konto selbst löschen</h2>
    <p>
        Eingeloggt kannst du dein Konto unter <a href="{{ route('profile.edit') }}">Profil</a> jederzeit selbst
        löschen. Alternativ siehe <a href="{{ route('legal.data-deletion') }}">Daten löschen</a>.
    </p>

    <h2>15. Sprachwahl, automatische Weiterleitung und Sprach-Cookie</h2>
    <p>
        Pixel Chaos gibt es auf Englisch (ohne Pfad-Präfix, z. B. „/“), Deutsch („/de“) und Spanisch („/es“).
        Die Sprache wird über die Adresse (URL) bestimmt.
    </p>
    <p>
        Beim ersten Aufruf der Startseite leiten wir dich automatisch in die passende Sprache weiter. Dazu
        ermitteln wir anhand deiner IP-Adresse das Land mit einer <strong>lokalen Datenbank</strong>
        (DB-IP „IP to Country Lite“). Deine IP-Adresse wird dafür <strong>nicht an Dritte weitergegeben</strong>
        und <strong>nicht gespeichert</strong>; sie wird nur flüchtig im Arbeitsspeicher verarbeitet. Ist keine
        Zuordnung möglich, nutzen wir ersatzweise den Accept-Language-Header deines Browsers. Bots und
        Suchmaschinen-Crawler werden nicht weitergeleitet. Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO; unser
        berechtigtes Interesse ist eine nutzerfreundliche Darstellung in deiner Sprache. Du kannst die Sprache
        jederzeit über den Sprachumschalter ändern. Damit kannst du der Weiterleitung auch widersprechen
        (Art. 21 DSGVO); deine Wahl wird dann respektiert.
    </p>
    <p>
        Wenn du bewusst eine Sprache wählst, setzen wir das funktionale Cookie <code>pixel_locale</code>
        (Dauer: 1 Jahr, HttpOnly, SameSite=Lax) und speichern die Sprache zusätzlich in deiner Sitzung. Das
        Cookie wird nur nach dieser bewussten Wahl gesetzt, dient allein dazu, deine Sprache zu merken, und ist
        technisch erforderlich im Sinne des § 25 Abs. 2 Nr. 2 TDDDG. Eine Einwilligung ist dafür nicht nötig.
    </p>

    <h2>16. Quellennachweis</h2>
    <p>
        IP Geolocation by <a href="https://db-ip.com" target="_blank" rel="noopener noreferrer">DB-IP.com</a>
        (Lizenz: <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener noreferrer">CC BY 4.0</a>).
    </p>

    <h2>17. Maßgebliche Fassung</h2>
    <p>
        Rechtsverbindlich ist die deutsche Fassung dieser Datenschutzerklärung. Die englische und die
        spanische Fassung sind Übersetzungen und dienen nur der Information.
    </p>

    <h2>18. Änderungen</h2>
    <p>Wir passen diese Erklärung an, wenn sich unser Angebot oder die Rechtslage ändert.</p>
@endsection
