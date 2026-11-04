@extends('legal.layout')

@section('title', 'Häufige Fragen (FAQ)')

@php($max = number_format((int) config('payments.limits.max_credits'), 0, ',', '.'))

@section('content')
    <div class="faq">
        <h2>Pixel</h2>

        <details open>
            <summary>Wie viele Pixel kann ich kostenlos setzen?</summary>
            <p>Als Gast hast du einmalig 50 Pixel. Mit einem kostenlosen Konto bekommst du einen Vorrat von bis zu 50 Pixeln, der alle 30 Sekunden um 1 nachwächst.</p>
        </details>

        <details>
            <summary>Was passiert, wenn mein kostenloser Vorrat leer ist?</summary>
            <p>Dann wächst alle 30 Sekunden ein Pixel nach. Du kannst warten oder Extra-Pixel im Shop kaufen und sofort weitermachen.</p>
        </details>

        <details>
            <summary>Was sind Extra-Pixel?</summary>
            <p>Extra-Pixel sind gekaufte Pixel. Sie werden getrennt von deinem kostenlosen Vorrat gespeichert, verfallen nicht und stehen sofort zur Verfügung.</p>
        </details>

        <details>
            <summary>Welche Pixel werden zuerst verbraucht – die kostenlosen oder die gekauften?</summary>
            <p>Immer zuerst der kostenlose Vorrat. Erst wenn er leer ist, werden Extra-Pixel genutzt. So wächst dein kostenloser Vorrat sofort wieder nach und du verschenkst keinen Nachwuchs, solange du Extra-Pixel nutzt.</p>
        </details>

        <details>
            <summary>Wie lese ich den Zähler unten rechts?</summary>
            <p>Die große Zahl (z. B. <strong>50/50</strong>) zeigt deinen kostenlosen Vorrat: aktuell verfügbar / Maximum. Die kleine gelbe Zahl darunter (z. B. <strong>+1.000</strong>) sind deine gekauften Extra-Pixel.</p>
        </details>

        <details>
            <summary>Gibt es eine Obergrenze für Extra-Pixel?</summary>
            <p>Ja, du kannst höchstens {{ $max }} Extra-Pixel besitzen. Pakete, die dich über diese Grenze bringen würden, sind im Shop ausgegraut. Setze einige Pixel, dann kannst du nachkaufen.</p>
        </details>

        <details>
            <summary>Was ist, wenn ich während der Zahlung das Limit erreiche?</summary>
            <p>Dann bekommst du die gekauften Pixel trotzdem gutgeschrieben – du verlierst nichts.</p>
        </details>

        <h2>Die Wand</h2>

        <details>
            <summary>Kann mein Pixel überschrieben werden?</summary>
            <p>Ja, jeder Pixel kann von jedem überschrieben werden. Frisch gesetzte Pixel sind aber 10 Minuten für andere gesperrt, damit du sie kurz verteidigen kannst. Du selbst kannst deinen gerade gesetzten Pixel in dieser Zeit weiter ändern (jede Änderung kostet einen Pixel aus deinem Vorrat). Auf einem gesperrten Pixel siehst du seine Koordinaten und wann er wieder frei ist.</p>
        </details>

        <details>
            <summary>Warum erscheint mein Pixel erst später im Hintergrundbild?</summary>
            <p>Dein Pixel ist sofort gespeichert und für alle sichtbar, die den Ausschnitt ansehen. Das große Hintergrundbild der Wand wird aus Kacheln zusammengesetzt, die alle 10 Minuten neu erzeugt werden. Geänderte Pixel und Wandvergrößerungen erscheinen dort daher mit bis zu 10 Minuten Verzögerung.</p>
        </details>

        <details>
            <summary>Was bringt die Wandvergrößerung?</summary>
            <p>Mit einer Erweiterung wächst die Wand nach rechts oder unten und der neue Platz steht allen Spieler:innen zur Verfügung.</p>
        </details>

        <h2>Konto, Zahlung und Daten</h2>

        <details>
            <summary>Brauche ich ein Konto zum Kaufen?</summary>
            <p>Ja. So landen deine Käufe sicher in deinem Profil. Das Konto ist kostenlos (E-Mail, Google oder Facebook).</p>
        </details>

        <details>
            <summary>Kann ich einen Kauf widerrufen?</summary>
            <p>Bei Pixel-Guthaben kann das Widerrufsrecht nach deiner ausdrücklichen Zustimmung und Bestätigung mit Beginn der Ausführung erlöschen. Bei einer Leinwanderweiterung erlischt es gegebenenfalls erst nach vollständiger Erbringung. Vor der Zahlung bitten wir dich um die gesetzlich erforderlichen Erklärungen. Details stehen in den <a href="{{ route('legal.terms') }}#withdrawal">Nutzungsbedingungen</a>.</p>
        </details>

        <details>
            <summary>Wie lösche ich mein Konto?</summary>
            <p>Unter <a href="{{ route('legal.data-deletion') }}">Daten löschen</a> erfährst du, wie. Noch nicht verbrauchte Extra-Pixel verfallen dabei.</p>
        </details>

        <details>
            <summary>Wie melde ich unzulässige Inhalte?</summary>
            <p>Schreib uns mit der Position (x/y) auf der Wand und dem Grund an <x-legal-value key="email" />.</p>
        </details>
    </div>

    <p>Weitere Informationen: <a href="{{ route('legal.terms') }}">Nutzungsbedingungen</a>, <a href="{{ route('legal.privacy') }}">Datenschutzerklärung</a>.</p>
@endsection
