<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Pixel Chaos</title>
        <meta property="og:type" content="website">
        <meta property="og:title" content="Pixel Chaos – baut gemeinsam ein Bild oder führt Pixelkrieg">
        <meta property="og:description" content="Schau dir diesen Ausschnitt der Pixelwand an und mach mit!">
        <meta property="og:url" content="{{ url()->full() }}">
        @if (! empty($shareImage))
            <meta property="og:image" content="{{ $shareImage }}">
            <meta name="twitter:card" content="summary_large_image">
        @endif
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="wall-page">
        <div class="pixel-shell wall-shell">
            <header class="pixel-topbar minimal wall-menu">
                <button type="button" class="wall-menu-toggle secondary-button" id="wallMenuToggle" aria-expanded="false" aria-controls="wallMenuPanel">
                    <span class="wall-menu-icon" aria-hidden="true"><span></span><span></span><span></span></span>
                    <span>Menü</span>
                </button>
                <div class="wall-menu-panel hidden" id="wallMenuPanel">
                    <div class="brand">
                        <small>Live World</small>
                        <strong>PIXEL CHAOS</strong>
                    </div>
                    <nav class="top-actions wall-menu-links" aria-label="Hauptnavigation">
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ route('profile.edit') }}">Profil</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit">Abmelden</button>
                                </form>
                            @else
                                <a href="{{ route('login') }}">Anmelden</a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="primary-button">Registrieren</a>
                                @endif
                            @endauth
                        @endif
                        <a href="{{ route('gallery') }}">Galerie</a>
                        <button type="button" class="secondary-button" id="openRulesBtn">Spielregeln</button>
                        <button type="button" class="secondary-button" id="shareViewBtn">🔗 Ausschnitt teilen</button>
                        <a class="secondary-button" id="shareFacebookLink" href="https://www.facebook.com/sharer/sharer.php" target="_blank" rel="noopener noreferrer">Auf Facebook teilen</a>
                        <button type="button" class="primary-button" id="openShopBtn">Pixel kaufen</button>
                    </nav>
                    <details class="feedback-details">
                        <summary>Feedback senden</summary>
                        <form id="feedbackForm" class="feedback-form">
                            @csrf
                            <label for="feedbackEmail">E-Mail (optional)</label>
                            <input id="feedbackEmail" name="email" type="email" maxlength="255" autocomplete="email">
                            <label for="feedbackMessage">Dein Feedback</label>
                            <textarea id="feedbackMessage" name="message" rows="4" maxlength="2000" required></textarea>
                            <div class="feedback-honeypot" aria-hidden="true">
                                <label for="feedbackWebsite">Dieses Feld leer lassen</label>
                                <input id="feedbackWebsite" name="website" type="text" tabindex="-1" autocomplete="off">
                            </div>
                            <button type="submit" class="primary-button" id="feedbackSubmitBtn">Feedback senden</button>
                            <p id="feedbackStatus" class="feedback-status" role="status" aria-live="polite"></p>
                        </form>
                    </details>
                </div>
            </header>

            <main class="canvas-stage">
                <div class="canvas-shell">
                    <div id="canvasWorld" class="canvas-world">
                        <div id="wallBackground" class="wall-background" aria-hidden="true"></div>
                        <canvas id="pixelCanvas" width="1024" height="1024" aria-label="Pixel-Wand"></canvas>
                    </div>
                    <div id="pixelLockTooltip" class="pixel-lock-tooltip hidden" role="status" aria-live="polite"></div>
                    <div class="wall-extension-controls" aria-label="Pixelwandnavigation">
                        <button type="button" class="secondary-button wall-nav wall-nav-up" id="navigateUpBtn" aria-label="300 Pixel nach oben">↑</button>
                        <button type="button" class="secondary-button wall-nav wall-nav-right" id="navigateRightBtn" aria-label="300 Pixel nach rechts">→</button>
                        <button type="button" class="secondary-button wall-nav wall-nav-down" id="navigateDownBtn" aria-label="300 Pixel nach unten">↓</button>
                        <button type="button" class="secondary-button wall-nav wall-nav-left" id="navigateLeftBtn" aria-label="300 Pixel nach links">←</button>
                        <button type="button" class="wall-extension-zone wall-extension-zone-right hidden" id="extendWallRightBtn" aria-label="Pixel kaufen: Pixelwand nach rechts erweitern">
                            <span class="wall-extension-plus" aria-hidden="true">+</span>
                            <span class="wall-extension-label"><strong>Pixel kaufen</strong><small>Wand nach rechts erweitern – für alle</small></span>
                        </button>
                        <button type="button" class="wall-extension-zone wall-extension-zone-bottom hidden" id="extendWallDownBtn" aria-label="Pixel kaufen: Pixelwand nach unten erweitern">
                            <span class="wall-extension-plus" aria-hidden="true">+</span>
                            <span class="wall-extension-label"><strong>Pixel kaufen</strong><small>Wand nach unten erweitern – für alle</small></span>
                        </button>
                        <div class="wall-zoom-controls" role="group" aria-label="Leinwandzoom">
                            <button type="button" class="secondary-button" id="zoomOutBtn" aria-label="Verkleinern" disabled>−</button>
                            <button type="button" class="secondary-button" id="zoomResetBtn" aria-label="Zoom auf 100 Prozent zurücksetzen">100%</button>
                            <button type="button" class="secondary-button" id="zoomInBtn" aria-label="Vergrößern">+</button>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <div id="reservationLoadingOverlay" class="reservation-loading-overlay" role="status" aria-live="polite" aria-label="Viewport wird geladen">
            <span id="reservationLoadingSpinner" class="reservation-loading-spinner" aria-hidden="true"></span>
            <span id="reservationLoadingMessage">Viewport wird geladen …</span>
            <button type="button" id="reservationLoadingRetry" class="secondary-button hidden">Neu laden</button>
        </div>

        <p id="statusText" class="status-live-region" role="status" aria-live="polite"></p>

        @if (session('purchase'))
            <div class="purchase-banner purchase-{{ session('purchase.status') }}" role="status" id="purchaseBanner">
                <span>{{ session('purchase.message') }}</span>
                <button type="button" class="secondary-button" id="purchaseBannerClose">OK</button>
            </div>
        @endif

        <div id="limitModal" class="shop-modal hidden" role="dialog" aria-modal="true" aria-labelledby="limitTitle">
            <div class="shop-card limit-card">
                <h3 id="limitTitle">Nächstes Pixel noch nicht möglich</h3>
                <p id="limitText"></p>
                <p class="limit-countdown" id="limitCountdown" aria-live="off"></p>
                <div class="limit-actions" id="limitGuestActions">
                    <a href="{{ route('register') }}" class="primary-button">Kostenlos registrieren</a>
                    <a href="{{ route('login') }}" class="secondary-button">Einloggen</a>
                </div>
                <div class="limit-actions hidden" id="limitUserActions">
                    <button type="button" class="primary-button" id="limitBuyBtn">100 Extra-Pixel kaufen</button>
                </div>
                <button type="button" class="secondary-button limit-close" id="limitCloseBtn">Okay, ich warte</button>
            </div>
        </div>

        <div id="shopModal" class="shop-modal hidden" role="dialog" aria-modal="true" aria-labelledby="shopTitle">
            <div class="shop-card">
                <div class="editor-header">
                    <h3 id="shopTitle">Pixel kaufen</h3>
                    <button type="button" id="closeShopBtn" class="icon-button" aria-label="Shop schließen">×</button>
                </div>
                <p class="shop-credits" id="shopCredits"></p>
                <div class="shop-products" id="shopProducts"></div>
                <div class="shop-checkout hidden" id="shopCheckout">
                    <h4>Bezahlen mit</h4>
                    <div class="shop-providers" id="shopProviders"></div>
                    <p class="shop-note">Du wirst zum Zahlungsanbieter weitergeleitet. Nach der Zahlung kommst du automatisch zurück.</p>
                </div>
                <div class="shop-checkout hidden" id="shopGuestNotice">
                    <p>Zum Kaufen brauchst du ein kostenloses Konto – so landen deine Käufe sicher in deinem Profil.</p>
                    <div class="limit-actions">
                        <a href="{{ route('register') }}" class="primary-button">Kostenlos registrieren</a>
                        <a href="{{ route('login') }}" class="secondary-button">Einloggen</a>
                    </div>
                </div>
                <p class="shop-error hidden" id="shopError" role="alert"></p>
            </div>
        </div>

        <div class="color-bar" id="colorBar" role="toolbar" aria-label="Farbauswahl">
            <div class="neighborhood">
                <canvas id="neighborhoodCanvas" width="54" height="54" aria-label="Umgebung des Pixels unter dem Cursor"></canvas>
                <span class="neighborhood-label" id="neighborhoodLabel">–</span>
            </div>
            <div class="popup-color-row" id="popupColorPalette">
                @foreach (['#ff6b6b','#ffd93d','#48dbfb','#1dd1a1','#c56cf0','#ff9ff3','#2d3436','#ffffff','#000000','#ff0000','#00aa00','#0044ff','#ff8800','#8b4513'] as $color)
                    <button class="color-button" type="button" data-color="{{ $color }}" style="background: {{ $color }};" aria-label="Farbe {{ $color }}"></button>
                @endforeach
            </div>
            <div class="color-picker-row">
                <input type="color" id="colorPicker" class="color-picker" value="#ff6b6b" aria-label="Beliebige Farbe wählen">
                <input type="text" id="colorHexInput" class="color-hex-input" value="#ff6b6b" maxlength="7" spellcheck="false" aria-label="Hex-Farbcode">
                <span class="active-color" id="activeColorLabel">#ff6b6b</span>
            </div>
            <div class="pixel-counter" id="pixelCounter" aria-live="polite">
                <strong id="pixelCounterValue">–</strong>
                <span>Pixel übrig</span>
                <small id="pixelCounterNote"></small>
            </div>
        </div>
        <div id="cursorCoords" class="cursor-coords hidden" aria-hidden="true"></div>
        <span id="isAuthenticated" data-auth="{{ Auth::check() ? '1' : '0' }}" style="display:none;"></span>

        <footer class="site-footer">
            <div class="footer-card">
                <h4>Impressum & Bildnachweis</h4>
                <p>
                    <strong>Urheber- und Lizenznennung:</strong>
                    <em>Official Presidential Portrait of President Donald J. Trump (2025)</em>.
                    Quelle: White House / Wikimedia Commons.
                    Lizenz: <a href="https://commons.wikimedia.org/wiki/File:Official_Presidential_Portrait_of_President_Donald_J._Trump_(2025).jpg" target="_blank" rel="noopener noreferrer">Public domain</a> (Werk der US-Bundesregierung, Titel 17, U.S. Code § 105).
                    Weitere Details: <a href="https://commons.wikimedia.org/wiki/File:Official_Presidential_Portrait_of_President_Donald_J._Trump_(2025).jpg" target="_blank" rel="noopener noreferrer">Wikimedia Commons Datei</a>.
                </p>
            </div>
        </footer>

        <div id="cookieBanner" class="cookie-banner" role="dialog" aria-live="polite">
            <div class="cookie-content">
                <div>
                    <strong>Cookies & Spielregeln</strong>
                    <p>Wir nutzen Cookies nur für die Sitzung und die erste Erklärung der Regeln. Deine Bestätigung bleibt lokal im Browser gespeichert.</p>
                </div>
                <button type="button" id="acceptCookiesBtn" class="primary-button">Akzeptieren</button>
            </div>
        </div>

        <div id="rulesModal" class="rules-modal" role="dialog" aria-modal="true" aria-labelledby="rulesTitle">
            <div class="rules-card">
                <h3 id="rulesTitle">Spielregeln: Pixelkrieg</h3>
                <ul>
                    <li><strong>Die Wand gehört dem, der sie sich nimmt.</strong> Jeder Pixel kann von jedem überschrieben werden – auch deiner.</li>
                    <li><strong>Bau mit anderen ein Bild</strong> – oder <strong>zerstöre das Werk deiner Gegner</strong> und führe deinen eigenen Pixelkrieg. Gemeinsam oder gegeneinander: du entscheidest.</li>
                    <li>Wähle einen Pixel, färbe ihn in einer beliebigen Farbe (Farbwähler oder Hex-Code) und setze ihn.</li>
                    <li>Als Gast hast du einmalig 50 Pixel. Danach brauchst du ein kostenloses Konto (E-Mail, Google oder Facebook).</li>
                    <li>Mit Konto wächst alle 30 Sekunden ein Pixel nach – maximal 50 im Vorrat. Wer wartet, schlägt später härter zu.</li>
                    <li>Frisch gesetzte Pixel sind kurz gesperrt (10 Minuten) – verteidige, was du gebaut hast, und hol dir zurück, was dir genommen wurde.</li>
                    <li>Keine Hassbilder, keine verbotenen Symbole, keine Beleidigungen von Personen. Verstöße werden gelöscht.</li>
                    <li>Die Wall lässt sich bis 800 % zoomen – für den Feinschliff und für den Angriff auf einzelne Pixel.</li>
                </ul>
                <button type="button" id="closeRulesBtn" class="primary-button">Verstanden</button>
            </div>
        </div>
    </body>
</html>
