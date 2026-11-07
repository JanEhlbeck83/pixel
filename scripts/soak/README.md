# PixRiot: 50 Nutzer, 14 Tage, ohne Shop

## Wann startet der Test?

**Ein Pull, Build oder Deployment startet nichts.** Erst `python3 run.py ...`
oder der ausdrueckliche Start des unten beschriebenen systemd-Dienstes erzeugt
Last. Es wurde kein Test gegen staging.pixriot.online gestartet.

Das Paket hat keine zusaetzlichen Python-Abhaengigkeiten (Python 3.11+).
Der Lastgenerator braucht OpenSSH und sollte auf einem **anderen Rechner/VPS**
laufen, nicht auf dem zu messenden 2-Kern-/4-GB-Server. Er muss fuer zwei Wochen
eingeschaltet bleiben. Ein geschlossener Laptop kann den Test nicht fortsetzen.
Der Zielserver braucht Linux, Docker Compose, Python 3, einen eingerichteten
SSH-Zugang und einen lesbaren Datenpfad `/opt/pixel-data/staging`.

## 1. Staging bewusst isolieren

Auf dem Zielserver den Projektstand pullen und wie gewohnt deployen.
Vorher Staging stoppen und eine **neue, leere PostgreSQL-Datenbank** bereitstellen,
in der noch nie reale Konten, Bestellungen oder Bilder lagen. Keine Live-Kopie
verwenden. Vorhandene Daten weder loeschen noch ueberschreiben.
Auch Staging-Uploads, Tiles, Snapshots, Cache und Sessions muessen von Live
getrennt bleiben. Das bestehende Staging-Compose-Projekt heisst `pixel-staging`.
Eine andere Datenbank auf dem laufenden alten Postgres-Volume wird **nicht**
allein durch Aendern von `POSTGRES_DB` angelegt; explizit neu bereitstellen.

In der **nur fuer dieses Staging** verwendeten Umgebung setzen:

```dotenv
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://staging.pixriot.online
SOAK_TEST_ENABLED=true
MAIL_MAILER=log
PAYMENTS_FAKE_ENABLED=false
```

Keine echten Zahlungsanbieter-Schluessel in dieser Testumgebung hinterlegen.
SMTP und Webhooks fuer diese Instanz nicht aktivieren. Zugriff ueber den
Staging-Basisschutz beschraenken; keine normalen Nutzer in dieses Staging lassen.
DNS, gueltiges HTTPS-Zertifikat und Proxy muessen vorher funktionieren.
Der Runner akzeptiert ausschliesslich `https://staging.pixriot.online`;
TLS-Pruefung wird nicht abgeschaltet.

Dann mit eurer bestehenden Compose-/Env-Konfiguration, **nur in der neuen DB**:

```bash
docker compose -p pixel-staging -f docker-compose-staging.yml up -d --build app nginx scheduler
docker compose -p pixel-staging -f docker-compose-staging.yml exec -T app php artisan migrate --force
docker compose -p pixel-staging -f docker-compose-staging.yml exec -T app php artisan config:clear
docker compose -p pixel-staging -f docker-compose-staging.yml exec -T --user www-data app php artisan soak:prepare --confirm-isolated
docker compose -p pixel-staging -f docker-compose-staging.yml exec -T --user www-data app php artisan wall:tiles --full
```

Die normalen Migrationen nur auf dieser neuen Testdatenbank ausfuehren,
nicht ungeprueft auf Live. `app`, `scheduler` und `nginx` muessen dieselben
Staging-Daten und Tiles sehen. Scheduler eingeschaltet lassen: Tile-Erzeugung,
Snapshots, Analytics-Bereinigung und Projektpruefungen gehoeren zur Serverlast.
Bei PostgreSQL-Backups und anderen bestehenden Serverjobs deren normale
Betriebszeiten beibehalten, damit Konkurrenz mitgemessen wird.

`soak:prepare` verweigert reale/bereits gefuellte Datenbanken und vorhandene
Testmanifeste. Es erzeugt genau 50 verifizierte Konten mit zufaelligen,
einzelnen Passwoertern, zwei Gruppen und zwei aktive 32x32-Projekte.
Es verschickt keine Mails und legt keine Bestellungen an.
Die leere Test-Wall wird bewusst auf den aktuellen grossen Stand
3840x2160 gesetzt, nicht auf den kleineren 1024x1024-Migrationsstandard.
Damit haben Hintergrundbilder und Snapshot-Jobs die passende Bildgroesse.
Nur diese Testprojekte bekommen ein 16-Tage-Malfenster, damit die normale
Projektfrist den Dauertest nicht vorzeitig beendet. Quoten, SQL-Sperren,
Authentifizierung, CSRF und HTTP-Ratenlimits bleiben unveraendert.
Normale Projekte der neuen Planung verfallen nach sieben Tagen ab Start.
Die laengere Frist ist ausschliesslich fuer diese isolierten Testfixtures,
ohne Voting; `soak:prepare` ist in Produktion und normalen Datenbanken gesperrt.

## 2. Manifest sicher zum Lastgenerator kopieren

Die Datei `storage/app/private/soak/manifest.json` enthaelt **Testpasswoerter**.
Sie ist privat, aus Docker-Builds ausgeschlossen und darf nicht in Git,
Chat, oeffentliche Downloads oder Ergebnisarchive. Keine realen Zugangsdaten
hier eintragen. Das Manifest laeuft nach 16 Tagen ab; Test zeitnah vorbereiten.

Beispiel auf dem Server, in einem privaten Arbeitsverzeichnis:

```bash
umask 077
docker compose -p pixel-staging -f docker-compose-staging.yml exec -T --user www-data app \
  cat storage/app/private/soak/manifest.json > "$HOME/pixriot-soak-manifest.json"
```

Diese Datei per SCP/SFTP auf den Generator uebertragen, dort als
`/var/lib/pixriot-soak/manifest.json` mit Besitzer `pixriot-soak` und Modus 600
ablegen. Die Serverkopie im Container bleibt fuer den geschuetzten
Identitaets-Endpunkt erforderlich.

## 3. Lastgenerator und SSH vorbereiten

Auf einem separaten Linux-Rechner einen unprivilegierten Benutzer
`pixriot-soak` mit eigenem Homeverzeichnis anlegen. Den Projektordner
`scripts/soak` nach `/opt/pixriot-soak/scripts/soak` kopieren. Verzeichnis
`/var/lib/pixriot-soak` fuer diesen Benutzer mit Modus 700 anlegen.

In dessen SSH-Konfiguration einen Alias `pixriot-staging` einrichten
(HostName, User, IdentityFile passend zu deinem Server).
Den Server-Fingerabdruck **vorher vertrauenswuerdig pruefen**, Host-Key speichern
und passwortlosen Batch-Zugriff testen:

```bash
sudo -u pixriot-soak ssh -o BatchMode=yes -o StrictHostKeyChecking=yes pixriot-staging true
```

Der SSH-Benutzer auf dem Zielserver braucht Zugriff auf `/proc`, den Datenpfad
und Docker. **Docker-Zugriff entspricht praktisch Root-Rechten**: nur einen
eigens verwalteten, vertrauenswuerdigen Monitoring-Zugang verwenden.
Der Probe liest nur ausgewaehlte Werte, keine Umgebungsvariablen oder Inhalte.
Hostmessung umfasst auch Live und andere Dienste auf demselben Server;
Containerdetails beziehen sich auf das Compose-Projekt `pixel-staging`.

Den Nginx-Basisschutz fuer den Runner in einer privaten Datei
`/etc/pixriot-soak.env` (root:root, 600) konfigurieren:

```dotenv
SOAK_BASIC_AUTH=dein-staging-benutzer:dein-staging-passwort
```

Keine Zugangsdaten in Kommandozeilen oder Unit-Dateien schreiben.
Diese Variablen-Datei liest systemd fuer den unprivilegierten Dienst.
Fuer den manuellen Probelauf die Variable im privaten Benutzerkontext setzen;
kein Passwort in der Shell-History speichern. Beispiel: `read -rs SOAK_BASIC_AUTH`,
anschliessend `export SOAK_BASIC_AUTH`.

## 4. Erst einen kurzen Probelauf ausfuehren

Im Kontext von `pixriot-soak`, mit gesetztem `SOAK_BASIC_AUTH`:

```bash
cd /opt/pixriot-soak/scripts/soak
python3 run.py --manifest /var/lib/pixriot-soak/manifest.json \
  --output /var/lib/pixriot-soak/smoke-results \
  --ssh-host pixriot-staging --confirm-isolated --smoke
python3 report.py /var/lib/pixriot-soak/smoke-results
```

Das sind drei Testkonten fuer 60 Sekunden, **mit echten Test-Schreibzugriffen**.
Nur bei Status `completed`, ohne HTTP-Fehler und mit erfolgreichen Mal-/Chat-
Anfragen fortfahren. Falls zufaellig kein Projekt besucht wurde, den Probelauf
in einem neuen Ergebnisverzeichnis wiederholen. Sein Verdict bleibt bewusst
`INCOMPLETE`: Eine Minute ist kein 14-Tage-Kapazitaetsnachweis.
Fehler beheben, bevor der Dauertest gestartet wird. Nichts wird automatisch
an der Serverkonfiguration optimiert oder an Daten geloescht.

## 5. Genau hier startest du den 14-Tage-Test

Die mitgelieferte Unit auf dem **Lastgenerator**, nicht auf dem Zielserver,
nach `/etc/systemd/system/pixriot-soak.service` kopieren.
Pfade, Dienstbenutzer, SSH-Alias und Env-Datei vorher kontrollieren:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now pixriot-soak.service
sudo systemctl status pixriot-soak.service
sudo journalctl -u pixriot-soak.service -f
```

**`enable --now` startet den Test.** Die ersten fuenf Minuten fahren
gleichmaessig auf 50 Sitzungen hoch. Danach folgen 14 volle Tage bei 50
gleichzeitig angemeldeten Spielern. Keine selbsttaetige Steigerung ueber 50.

Einzelne Spieler wechseln alle 5-15 Minuten zwischen Wall und Projekt.
Wall: Aenderungen 5 s, Vorrat 10 s, Dimensionen/Sperren 15 s, Reservierungen
30 s. Projekt: Vorrat/Chat 5 s. Heartbeat 15 s, Projektphasen 30 s.
Jeder Spieler malt etwa alle 35-65 Sekunden; Projektchat alle 4-10 Minuten,
solange das Projekt besucht wird. Unterschiedliche Startzeiten und kurze
Pausen vermeiden unnatuerliche synchrone Requestwellen.
Statische Build-Dateien werden je Sitzung einmal geladen, Wall-Kacheln
bei simuliertem Verschieben nach 60-120 Sekunden erneut angefragt.
Es gibt **keine Shop-, Checkout-, Zahlungs-, Registrierungs- oder Mailanfragen**.
Der Shop-Abruf der echten Wall wird bewusst ausgelassen.

Der HTTP-Test fuehrt kein Frontend-JavaScript aus. Er simuliert die
HTTP-Spielablaeufe, nicht 50 komplette grafische Browser. Kein Pinch-/FPS-Test.
Die wiederverwendeten eigenen Wall-Streifen vermeiden kuenstliche
Sperrkonflikte; gemeinsames Projektmalen erzeugt echte SQL-Konkurrenz.
Ueber zwei Wochen entstehen bei diesem Profil grob 1,2 Millionen
Malaktionen und rund 60.000 Chatnachrichten, dazu Activity-/Analytics-Daten.
Die Pixelkoordinaten werden wiederverwendet: Das fuellt nicht die ganze Wall
mit Millionen Pixel-Datensaetzen. Wachstum und freien Platz beobachten.
Reale bunte/grossflaechige Ausgangsdaten, gleichzeitiges Ueberschreiben
derselben Pixel und alle Gruppenphasen sind damit nicht vollstaendig abgedeckt.

## 6. Schutz, Stoppen und Wiederanlauf

- Servermessung alle 30 s: Host-CPU/RAM/Swap/Datenplatte/Load,
  Docker-CPU/RAM/Netz/Block-I/O, Container-Neustarts/OOM,
  DB-Groesse, Verbindungen, Transaktionen/Rollbacks/Deadlocks und Datensatzanzahlen.
- Abbruch nach fuenf schlechten Minuten: HTTP-Fehler >1%, p95 >2 s
  oder p99 >5 s (gesamt oder pro ausreichend haeufigem Ablauf).
- CPU oder RAM >95% fuer zehn Servermessungen; kritischer Plattenplatz
  (<10% oder <2 GiB), Container-Ausfall/Neustart/OOM oder Bestellungen:
  automatischer Abbruch. Drei fehlgeschlagene Servermessungen stoppen ebenfalls.
- Ein ausgefallener Spieler oder weniger als 50 responsive Spieler
  nach dem Hochfahren beendet den Test statt unbemerkt weniger Last zu erzeugen.
- Ergebnisplatte auf dem Generator <1 GiB frei: Abbruch.
- Nichts garantiert, dass andere Nutzer bis zum Abbruch keinerlei
  Verzoegerung bemerken. Deshalb ausschliesslich isoliert und unter Aufsicht starten.

Sofort stoppen:

```bash
sudo systemctl stop pixriot-soak
```

Alternativ als Dienstbenutzer `/var/lib/pixriot-soak/results/STOP` anlegen;
dies fuehrt zu einem **terminalen Abbruch**, kein automatischer Neustart.
Bereits laufende Requests koennen noch bis zum Timeout fertig werden.
Ein manueller Dienststopp setzt den Status `interrupted`. Ein spaeterer
`systemctl start` setzt denselben Lauf mit unveraendertem Endzeitpunkt fort.
Ausfallzeiten werden nicht als erfolgreiche Testzeit ausgegeben.
Ein Schutzabbruch (`aborted`, Exit 2) wird nicht automatisch neu gestartet.
Zwei Runner koennen nicht gleichzeitig dasselbe Ergebnisverzeichnis nutzen.
Niemals zwei unabhaengige Runner mit demselben Manifest starten.

Nach `completed` oder `aborted` keinen zweiten Lauf in dasselbe Verzeichnis
starten. Fuer einen neuen Test ein neues Ergebnisverzeichnis und ausreichend
lange gueltige Fixtures vorbereiten; alte Ergebnisse aufbewahren.

## 7. Ergebnisse lesen und zur Analyse weitergeben

Berichte jederzeit auf dem Generator erstellen, vorzugsweise nach Stop:

```bash
sudo -u pixriot-soak python3 /opt/pixriot-soak/scripts/soak/report.py \
  /var/lib/pixriot-soak/results
```

Beim Dienstende wird der Bericht automatisch erstellt.
Die Rohdaten werden taeglich in JSONL-Dateien aufgeteilt, pro HTTP-Minute
aggregiert und im Speicher begrenzt. Es werden keine Cookies, Passwoerter,
IP-Adressen oder Chattexte in Testberichte geschrieben.
`state.json` enthaelt Zeitplan/Status, `events-*.jsonl` Start-/Abbruchereignisse.

Zur spaeteren Analyse bereitstellen:

- `report.md`: lesbare Zusammenfassung und Antwortzeiten je Ablauf.
- `report.json`: maschinenlesbar, stuendliche Verlaeufe, schlechteste Fenster,
  Ressourcen-Spitzen, DB-Wachstum/Transaktionen und Testabdeckung.
- Bei Bedarf `http-*.jsonl`, `server-*.jsonl`, `events-*.jsonl`, `state.json`.
- **Niemals** Manifest, Env-Datei, SSH-Schluessel oder echte Nutzerdaten beilegen.

Ziel fuer fluessige Bedienung: Fehler <=0,1%, p95 Lesen <=500 ms,
p95 Malen/Chat/Reservieren <=750 ms, p99 je Ablauf <=1500 ms.
PASS erst nach 14 Tagen mit >=99,9% Zeitabdeckung aller 50 Sitzungen
und eingehaltenen Ablaufzielen. Ein schlechter einzelner Endpoint wird
nicht durch viele schnelle Polling-Anfragen verdeckt.
Ein bestandener Test ist eine Messung dieses Datenstands und Lastprofils,
keine Garantie fuer jedes Handy, jede Internetverbindung oder mehr als 50 Nutzer.
Bei gleichzeitiger Live-Nutzung geht diese zusaetzliche Last in Hostwerte ein.

Nach Abschluss `systemctl disable pixriot-soak`, `SOAK_TEST_ENABLED=false`
auf Staging setzen und deployen. Testdaten nur nach bewusster Archivierung
und ausdruecklicher Freigabe zuruecksetzen; das Paket loescht keine Test-DB.

## Automatisierte Pakettests (ohne Serverlast)

Im Projektverzeichnis:

```bash
python3 -m unittest discover -s tests -p test_soak.py -v
php artisan test --compact tests/Feature/SoakTest.php
```
