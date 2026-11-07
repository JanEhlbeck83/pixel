<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## 50-Nutzer-Dauertest (14 Tage, ohne Shop)

Vorbereitung, manueller Start, sicherer Dauerbetrieb auf einem separaten
Lastgenerator und Serverauswertung: [Dauertest-Anleitung](scripts/soak/README.md).
Ein Pull/Deployment startet keinen Test. Nur eine isolierte Staging-Datenbank
mit synthetischen Konten verwenden; niemals die Live-Datenbank.

## Mobile Spielsteuerung

Bis 760 CSS-Pixel sowie im Smartphone-Querformat bis 1024 Pixel Breite und
500 Pixel Hoehe verwenden Wall und Projekt-Malflaeche eine zweizeilige
Touchleiste: direkt sichtbare, separat scrollbare Farben und darunter
Werkzeuge beziehungsweise Malmodus. Die Farbkachel mit Plus oeffnet freie
Farbe, Hex-Eingabe und auf der Wall den optionalen Ziehmalen-Schalter.
Ein Tippen auf die Leinwand schliesst offene Farbeinstellungen, ohne
dabei einen Pixel zu setzen.
Der Wall-Vorrat steht separat oben; 48-Pixel-Randpfeile und Zoom bleiben
erreichbar. Sichere Bildschirmraender sind bei Menue und Leiste beruecksichtigt.

Im Wall-Ansichtsmodus verschiebt ein Finger die Ansicht lokal; erst am
Gestenende werden erforderliche Kacheln geladen. Tippen behaelt das bisherige
Verhalten. Malmodus, Pipette, Stempel, Melden und Reservieren haben Vorrang;
Verschieben sendet keine Pixel-Schreibanfragen.

Die Projektflaeche startet mobil im Verschiebemodus. "Malen" aktiviert
bewusst das Setzen; "Verschieben" kehrt zur sicheren Navigation zurueck.
Pipetten bleiben unabhaengig nutzbar. Eine uebernommene Farbe/Position
aktiviert wie bisher direkt das Malen; Desktop malt weiterhin unveraendert.
Ein Wechsel ueber die mobile Groessengrenze beendet laufende Projektstriche.

Die gemeinsame Navigation wird einmal ueber einen eigenen Vite-Einstieg
initialisiert, auch in Galerie und Community. Mobil oeffnet ein Touchmenue
mit Hintergrund, Schliessen/Escape und Fokusfuehrung; Desktop behaelt das
Dropdown. Es gibt keine doppelte Inline-/Modulinitialisierung.

## Persoenliche 20x50-Entwuerfe und Pixelgalerie

"Mein Bild" in der Wall-Farbleiste oeffnet einen fest positionierten, von unten
ausfahrenden Editor. Das Raster bleibt 20 Pixel breit und 50 hoch. Zoom von
200-2000%, Plus/Minus und "Einpassen" aendern nur die Ansicht. Transparente
Leerstellen lassen die Wall unveraendert. Malen per Ziehen interpoliert die
besuchten Felder; Pfeiltasten und Leertaste erlauben Tastaturbedienung.
Die Palette waehlt die Zeichenfarbe, der Radierer entfernt Entwurfsfarbe.

Jedes Entwurfsfeld kostet beim ersten gespeicherten Bemalen einen Pixel aus
dem normalen Vorrat (Gratis vor Extra). Umfaerben, Radieren und Wiederbemalen
desselben Feldes sind bis zur Veroeffentlichung kostenlos, ohne Erstattung.
Ein Pinselstrich wird gesammelt mit einem PATCH gespeichert und bezahlt,
nicht mit einem Request pro Feld. Reicht der Vorrat nicht, wird der gesamte
Strich abgelehnt. Versionspruefung verhindert doppelte Abbuchungen und
stilles Ueberschreiben zwischen Tabs. Bei Netzfehlern bleiben ungespeicherte
Aenderungen fuer einen manuellen Speicherversuch erhalten; Navigation warnt.
Zuklappen loescht nichts. Es gibt keine Flaechenreservierung fuer Entwuerfe.

"Auf der Wall platzieren" aktiviert eine lokale Vorschau. Ein Wall-Klick
fixiert die Position; erst "Hier veroeffentlichen" sendet einen POST.
Alle bemalten Zielpixel werden gemeinsam unter dem vorhandenen Kachelmutex
auf Grenzen, Projektschutz und Bearbeitungssperren geprueft. Fehler lassen
den bezahlten Entwurf vollstaendig erhalten. Erfolgreiches Setzen schreibt
mit einem Upsert und kostet nichts zusaetzlich, auch bei leerem Vorrat.
Danach werden Entwurf und bezahlte Felder zurueckgesetzt: kein kostenloses
Mehrfachstempeln. Normales Wall-Malen ist waehrend Entwurfsbearbeitung gesperrt.

Konten speichern Entwuerfe in der Datenbank. Gaeste erhalten ein verschluesseltes
HttpOnly-Sessioncookie, kein localStorage; ohne Cookie ist ihr Entwurf nicht
wiederherstellbar. Browser koennen Sessioncookies bei Sitzungswiederherstellung
behalten. Verwaiste Gastentwuerfe laufen nach 30 Tagen ohne Bearbeitung ab und
werden taeglich bereinigt. Anmeldung und Registrierung (auch Social-Login)
uebernehmen den Gastentwurf samt bezahlten Feldern. Ein vorhandener Kontoentwurf
wird nicht ersetzt: importierte Entwuerfe sind unter "1 Pixel je neuem Feld - Infos"
auswaehlbar. Neue Konten starten ohnehin mit bis zu 50 Gratispixeln; es gibt
keinen zusaetzlichen wiederholbaren Login-Bonus.

Veroeffentlichte Kontoentwuerfe werden atomar als eigene, zunaechst private
Galeriebilder archiviert. Das Profil verlinkt die eigene Galerie mit
Privat/Oeffentlich-Schalter und Loeschfunktion. Oeffentliche Bilder erscheinen
in der allgemeinen Galerie und in `/artists/{user}/gallery`; private Bilder
und PNGs sind nur fuer den Besitzer abrufbar, ohne oeffentliche Dateipfade.
Loeschen entfernt das Archivbild, nicht die bereits gesetzten Wall-Pixel.
Kontoloeschung entfernt Entwuerfe und Galerieeintraege per Fremdschluessel.
Die Migration `2026_11_06_000004_create_wall_drafts_and_artworks.php` ist erforderlich.

## Gruppen- und Projekthinweise auf der Wall

Der obere Gruppenhinweis verlinkt fuer Mitglieder die eigene Gruppe mit Namen;
ohne Mitgliedschaft bleibt die Gruppenentdeckung sichtbar. Ein zweiter Hinweis
zeigt alle eigenen laufenden Projektteilnahmen der aktuellen Gruppe mit Phase
und passender Aktion (Anmeldung, geplanter Start oder Weiterpixeln).
Nach Ende der Malfrist verweist er auf den Projektabschluss statt aufs Malen.
Beide Hinweise sind unabhaengig per X fuer die aktuelle Seite ausblendbar,
ohne Browser-Speicherung. Beim erneuten Seitenaufruf werden sie wieder angezeigt.
In der Reservierungsansicht bleiben beide ausgeblendet.

## Zeichenstempel auf der Wall

Die Farbleiste bietet neben der Pipette einen Zeichenstempel fuer genau ein
Zeichen A-Z oder 0-9. Klassisch, Schmal und Schraeg sind fuer alle Nutzer
verfuegbar. Der serverseitig erzeugte Bitmap-Katalog bietet nur Hoehen an,
bei denen jedes Zeichen in jeder Schrift strikt weniger als 50 Pixel hat.
Aktuell sind das 5-11 px, mit maximal 43 Schriftpixeln. 12-14 px werden nicht
angeboten. Die Hoehe bezeichnet die Rasterhoehe, nicht typografische Punkte.

Die Auswahl zeigt die maximale Pixelzahl je Zeichen und sperrt aktuell zu
teure Optionen anhand des gemeinsamen Konto-/Gastvorrats. Die positionierte
Vorschau berechnet dagegen die exakten Kosten: Bereits gleichfarbige Pixel
sind kostenlos, transparente Hintergrundstellen bleiben unveraendert.
Nach Auswahl klappt das Menue zu einer kompakten Kostenanzeige ein, damit auch
mobil Platz zum Setzen bleibt. "Aendern" oeffnet die Auswahl erneut.
Escape oder erneuter Klick auf "Zeichen" beendet das Werkzeug.

Vorschau, Kosten und lokale Sperrpruefung verwenden geladene Farben,
Bearbeitungssperren und Projektreservierungen ohne zusaetzliche Requests.
Noch nicht geladene Bereiche sind nicht bemalbar. Platzieren sendet genau
einen POST an `/api/pixels/glyph`, mit Schrift, Hoehe, Zeichen, Position,
Farbe und erwartetem Preis, niemals einer frei waehlbaren Pixelliste.
Der Server leitet alle Schriftpixel selbst ab, prueft alle betroffenen
Pixel einschliesslich gleichfarbiger Stellen unter dem gemeinsamen
Kachelmutex und schreibt in einer Transaktion mit einem Upsert.
Sperre, Reservierung, Randueberschreitung oder unzureichender Gesamtvorrat
verhindern das gesamte Zeichen ohne Teilabbuchung. Zuerst werden kostenlose
Pixel, danach Extra-Pixel verbraucht. Bei zwischenzeitlich geaendertem Preis
liefert HTTP 409 die aktuellen Farben zurueck; es gibt keine automatische
Wiederholung oder hoehere Abbuchung. Normales Malen und Zeichenrequests
werden im Browser nicht gleichzeitig ausgefuehrt.

Die Zusatzarbeit ist auf hoechstens 43 Schriftpixel pro Request begrenzt.
Der einmalig eingebettete Katalog umfasst etwa 85 kB unkomprimiertes JSON;
es gibt keine externen Schriftdateien. `WallGlyphTest` misst SQL-Anzahl und
Laufzeit fuer ein Standardzeichen sowie das groesste Zeichen ueber vier
Kacheln und prueft einen einzigen Pixel-Upsert. Diese SQLite-Testmessungen
sind keine Produktions- oder Lasttestprognose fuer PostgreSQL.

## PixRiot SEO

Die XML-Sitemap ist unter `/sitemap.xml` erreichbar und wird bei jedem Aufruf
aus den oeffentlichen Seiten und den konfigurierten Sprachen erzeugt.
Sie enthaelt Startseite, Pixelart, FAQ und Rechtstexte mit `hreflang`-Alternativen
einschliesslich `x-default`. Login, Profile, APIs und Weiterleitungen werden
nicht aufgenommen. Eine Generierung per Artisan oder Scheduler ist nicht erforderlich.
`public/robots.txt` verweist auf die Live-Sitemap unter
`https://pixriot.online/sitemap.xml`; diese URL kann in Google Search Console
eingereicht werden.

Die sichtbare Bezeichnung lautet in allen Sprachen "Pixelart". Die bestehenden
`/gallery`-URLs und Routennamen bleiben fuer vorhandene Links erhalten.
Startseite und Pixelart haben eigene SEO-Titel und Meta-Beschreibungen in
Deutsch, Englisch und Spanisch. Der Pixelart-Bereich hat eine lokalisierte
Hauptueberschrift und einen kurzen Einleitungstext; bestehende Share-Metadaten
fuer Ausschnitte der Wand bleiben erhalten.

Beim ersten Wandbesuch erscheint ein Willkommensbanner; seine Bestaetigung
und ungelesene Spielregeln. Erst nach optionaler Speicherzustimmung
(`pixriot-onboarding-storage-accepted`) werden Einfuehrung (`pixriot-welcome-seen`)
und Regeln (`pixriot-rules-seen`) dauerhaft in localStorage gespeichert.
Alte Cookiebestaetigungen gelten nicht als Zustimmung zum neuen Speicherzweck.
Nach "Spiel starten" folgen ungelesene Regeln. Erst ihre Bestaetigung gibt
die Wand frei. Hintergrundklick und Escape bestaetigen keine Regeln.
Der Cookiebanner liegt waehrenddessen hinter dem Sperrscreen und ist nicht
bedienbar. Nach der Freigabe bleibt er unten sichtbar, bis er bestaetigt wird;
seine Bestaetigung ist keine Voraussetzung zum Spielen. Ohne verfuegbaren
localStorage gelten Bestaetigungen nur fuer den aktuellen Seitenaufruf.
Der Menuebutton erhaelt nach der Einfuehrung keinen automatischen Fokus und
keinen Fokusrand. Angemeldete Nutzer koennen sich im Menue per
CSRF-geschuetztem POST-Formular abmelden.

"Ausschnitt teilen" nutzt zuerst natives Teilen, sonst die Zwischenablage.
Erfolg oder Kopierfehler werden sichtbar im Menue angezeigt; ein Linkfeld
ermoeglicht manuelles Kopieren. Abbruch des nativen Dialogs kopiert nichts.
Der Link behaelt Sprache, Ausschnittmitte und Zoom bei.

## Gruppenprojekte: direkte Anmeldung und geplanter Start

Gruppenmitglieder stellen ein Projekt mit festem Format (32-200 Pixel je
Seite), optionaler eigener Bildvorlage, Mindestbesetzung (mindestens drei
inklusive Projektleitung), erwarteter Arbeitsdauer (1-168 Stunden) und
optionalem Startzeitpunkt vor. Anmeldung direkt auf der Gruppenseite.
Pro Person ist genau eine laufende Teilnahme oder Projektleitung erlaubt,
einschliesslich Anmeldung und geplantem Start; pro Gruppe maximal fuenf Projekte.

Die Mindestbesetzung muss innerhalb von 24 Stunden erreicht sein. Ohne sie
verfaellt das Projekt. Sobald sie erreicht ist, startet das Projekt sofort
oder wartet auf den gewaehlten Zeitpunkt (hoechstens sieben Tage im Voraus).
Die erwartete Dauer ist nur eine Orientierung, keine automatische Stopfrist.
Ab tatsaechlichem Start bleiben sieben Tage zum Malen und zum manuellen
Abschluss durch die Projektleitung. Ohne Abschluss verfaellt das Projekt.
Verspaetete Scheduler-Laeufe verschieben geplante Start-/Endzeitpunkte nicht.

Nach erfolgreicher Anmeldung reserviert die Leitung die feste Projektflaeche
auf der Wall; bei geplantem Start ist dies bereits vorher moeglich.
Malen ist erst nach Start und Reservierung erlaubt. Die Vorlage wird nicht
automatisch aufgemalt. Private Pixel, Zoom, Pipetten, Touchsteuerung und
gemeinsamer Wall-/Projektvorrat bleiben erhalten. Der Projektchat kann schon
zur Vorbereitung genutzt werden. Nach Abschluss/Verfall bleibt er lesbar.
Projektleitung muss beim Abschluss explizit bestaetigen. Nach Veroeffentlichung
bleibt die Wallflaeche sieben Wochen reserviert; es gibt keine Voting-Verlaengerung.
Bei Abbruch, Verfall oder Gruppenaustritt der Leitung werden Projektplatz und
Flachenreservierung frei, Inhalte bleiben erhalten.

Keine Vorschlags-/Votingphasen, Enthaltungen, Einstimmigkeit, Nachbesetzungs-
oder Abschluss-Nachfristen und keine persoenliche Startpause mehr.
Alte vorbereitende Projekte werden durch die Migration archiviert; ihre
Vorlagen, Vorschlaege, Stimmen und Pixel werden nicht geloescht. Bereits
aktive Projekte behalten ihre Arbeit, erhalten aber die neue 7-Tage-Frist
ab ihrem bisherigen Start. Alte Datenbanktabellen bleiben als Historie;
die alten HTTP-Aktionen, Models, Oberflaechen und Tests sind entfernt/ersetzt.
Vor Deployment Backup erstellen und die neue Migration gezielt ausfuehren.
Veroeffentlichte Bilder liegen nun in einem persistenten `public-data`-Volume
von App und Scheduler und werden ueber den Projektbild-Endpunkt ausgeliefert.
Vor dem ersten Deployment bestehendes `storage/app/public` aus dem alten
App-Container sichern und nach dem Volume-Wechsel wiederherstellen; sonst
koennen alte Dateien durch den neuen Mount verdeckt werden. Nie `down -v`
zum Aktualisieren verwenden.

Tests: `php artisan test --compact tests/Feature/CommunitySignupTest.php`
und die bestehenden Wall-/Projekteditor-/Mobiletests. Der Scheduler
`community:advance-projects` prueft Anmeldung, geplanten Start und Verfall.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
