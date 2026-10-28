<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Pixel Chaos</title>
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ __('Pixel Chaos – baut gemeinsam ein Bild oder führt Pixelkrieg') }}">
        <meta property="og:description" content="{{ __('Schau dir diesen Ausschnitt der Pixelwand an und mach mit!') }}">
        <meta property="og:url" content="{{ url()->full() }}">
        <x-locale-head />
        <x-i18n-script />
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
                    <span>{{ __('Menü') }}</span>
                </button>
                <div class="wall-menu-panel hidden" id="wallMenuPanel">
                    <div class="brand">
                        <small>{{ __('Live World') }}</small>
                        <strong>PIXEL CHAOS</strong>
                    </div>
                    <nav class="top-actions wall-menu-links" aria-label="{{ __('Hauptnavigation') }}">
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ route('profile.edit') }}">{{ __('Profil') }}</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit">{{ __('Abmelden') }}</button>
                                </form>
                            @else
                                <a href="{{ route('login') }}">{{ __('Anmelden') }}</a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="primary-button">{{ __('Registrieren') }}</a>
                                @endif
                            @endauth
                        @endif
                        <a href="{{ route('gallery') }}">{{ __('Galerie') }}</a>
                        <button type="button" class="secondary-button" id="openRulesBtn">{{ __('Spielregeln') }}</button>
                        <button type="button" class="secondary-button" id="shareViewBtn">{{ __('🔗 Ausschnitt teilen') }}</button>
                        <a class="secondary-button" id="shareFacebookLink" href="https://www.facebook.com/sharer/sharer.php" target="_blank" rel="noopener noreferrer">{{ __('Auf Facebook teilen') }}</a>
                        <button type="button" class="primary-button" id="openShopBtn">{{ __('Pixel kaufen') }}</button>
                    </nav>
                    <x-language-switcher />
                    <details class="feedback-details">
                        <summary>{{ __('Feedback senden') }}</summary>
                        <form id="feedbackForm" class="feedback-form">
                            @csrf
                            <label for="feedbackEmail">{{ __('E-Mail (optional)') }}</label>
                            <input id="feedbackEmail" name="email" type="email" maxlength="255" autocomplete="email">
                            <label for="feedbackMessage">{{ __('Dein Feedback') }}</label>
                            <textarea id="feedbackMessage" name="message" rows="4" maxlength="2000" required></textarea>
                            <div class="feedback-honeypot" aria-hidden="true">
                                <label for="feedbackWebsite">{{ __('Dieses Feld leer lassen') }}</label>
                                <input id="feedbackWebsite" name="website" type="text" tabindex="-1" autocomplete="off">
                            </div>
                            <button type="submit" class="primary-button" id="feedbackSubmitBtn">{{ __('Feedback senden') }}</button>
                            <p id="feedbackStatus" class="feedback-status" role="status" aria-live="polite"></p>
                        </form>
                    </details>
                </div>
            </header>

            <main class="canvas-stage">
                <div class="canvas-shell">
                    <div id="canvasWorld" class="canvas-world">
                        <div id="wallBackground" class="wall-background" aria-hidden="true"></div>
                        <canvas id="pixelCanvas" width="1024" height="1024" aria-label="{{ __('Pixel-Wand') }}"></canvas>
                    </div>
                    <div id="pixelLockTooltip" class="pixel-lock-tooltip hidden" role="status" aria-live="polite"></div>
                    <div class="wall-extension-controls" aria-label="{{ __('Pixelwandnavigation') }}">
                        <button type="button" class="secondary-button wall-nav wall-nav-up" id="navigateUpBtn" aria-label="{{ __('300 Pixel nach oben') }}">↑</button>
                        <button type="button" class="secondary-button wall-nav wall-nav-right" id="navigateRightBtn" aria-label="{{ __('300 Pixel nach rechts') }}">→</button>
                        <button type="button" class="secondary-button wall-nav wall-nav-down" id="navigateDownBtn" aria-label="{{ __('300 Pixel nach unten') }}">↓</button>
                        <button type="button" class="secondary-button wall-nav wall-nav-left" id="navigateLeftBtn" aria-label="{{ __('300 Pixel nach links') }}">←</button>
                        <button type="button" class="wall-extension-zone wall-extension-zone-right hidden" id="extendWallRightBtn" aria-label="{{ __('Pixel kaufen: Pixelwand nach rechts erweitern') }}">
                            <span class="wall-extension-plus" aria-hidden="true">+</span>
                            <span class="wall-extension-label"><strong>{{ __('Pixel kaufen') }}</strong><small>{{ __('Wand nach rechts erweitern – für alle') }}</small></span>
                        </button>
                        <button type="button" class="wall-extension-zone wall-extension-zone-bottom hidden" id="extendWallDownBtn" aria-label="{{ __('Pixel kaufen: Pixelwand nach unten erweitern') }}">
                            <span class="wall-extension-plus" aria-hidden="true">+</span>
                            <span class="wall-extension-label"><strong>{{ __('Pixel kaufen') }}</strong><small>{{ __('Wand nach unten erweitern – für alle') }}</small></span>
                        </button>
                        <div class="wall-zoom-controls" role="group" aria-label="{{ __('Leinwandzoom') }}">
                            <button type="button" class="secondary-button" id="zoomOutBtn" aria-label="{{ __('Verkleinern') }}" disabled>−</button>
                            <button type="button" class="secondary-button" id="zoomResetBtn" aria-label="{{ __('Zoom auf 100 Prozent zurücksetzen') }}">100%</button>
                            <button type="button" class="secondary-button" id="zoomInBtn" aria-label="{{ __('Vergrößern') }}">+</button>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <div id="reservationLoadingOverlay" class="reservation-loading-overlay" role="status" aria-live="polite" aria-label="{{ __('Viewport wird geladen') }}">
            <span id="reservationLoadingSpinner" class="reservation-loading-spinner" aria-hidden="true"></span>
            <span id="reservationLoadingMessage">{{ __('Viewport wird geladen …') }}</span>
            <button type="button" id="reservationLoadingRetry" class="secondary-button hidden">{{ __('Neu laden') }}</button>
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
                <h3 id="limitTitle">{{ __('Nächstes Pixel noch nicht möglich') }}</h3>
                <p id="limitText"></p>
                <p class="limit-countdown" id="limitCountdown" aria-live="off"></p>
                <div class="limit-actions" id="limitGuestActions">
                    <a href="{{ route('register') }}" class="primary-button">{{ __('Kostenlos registrieren') }}</a>
                    <a href="{{ route('login') }}" class="secondary-button">{{ __('Einloggen') }}</a>
                </div>
                <div class="limit-actions hidden" id="limitUserActions">
                    <button type="button" class="primary-button" id="limitBuyBtn">{{ __('100 Extra-Pixel kaufen') }}</button>
                </div>
                <button type="button" class="secondary-button limit-close" id="limitCloseBtn">{{ __('Okay, ich warte') }}</button>
            </div>
        </div>

        <div id="shopModal" class="shop-modal hidden" role="dialog" aria-modal="true" aria-labelledby="shopTitle">
            <div class="shop-card">
                <div class="editor-header">
                    <h3 id="shopTitle">{{ __('Pixel kaufen') }}</h3>
                    <button type="button" id="closeShopBtn" class="icon-button" aria-label="{{ __('Shop schließen') }}">×</button>
                </div>
                <p class="shop-credits" id="shopCredits"></p>
                <div class="shop-products" id="shopProducts"></div>
                <div class="shop-checkout hidden" id="shopCheckout">
                    <h4>{{ __('Bezahlen mit') }}</h4>
                    <div class="shop-providers" id="shopProviders"></div>
                    <p class="shop-note">{{ __('Du wirst zum Zahlungsanbieter weitergeleitet. Nach der Zahlung kommst du automatisch zurück.') }}</p>
                </div>
                <div class="shop-checkout hidden" id="shopGuestNotice">
                    <p>{{ __('Zum Kaufen brauchst du ein kostenloses Konto – so landen deine Käufe sicher in deinem Profil.') }}</p>
                    <div class="limit-actions">
                        <a href="{{ route('register') }}" class="primary-button">{{ __('Kostenlos registrieren') }}</a>
                        <a href="{{ route('login') }}" class="secondary-button">{{ __('Einloggen') }}</a>
                    </div>
                </div>
                <p class="shop-error hidden" id="shopError" role="alert"></p>
            </div>
        </div>

        <div class="color-bar" id="colorBar" role="toolbar" aria-label="{{ __('Farbauswahl') }}">
            <div class="neighborhood">
                <canvas id="neighborhoodCanvas" width="54" height="54" aria-label="{{ __('Umgebung des Pixels unter dem Cursor') }}"></canvas>
                <span class="neighborhood-label" id="neighborhoodLabel">–</span>
            </div>
            <div class="popup-color-row" id="popupColorPalette">
                @foreach (['#ff6b6b','#ffd93d','#48dbfb','#1dd1a1','#c56cf0','#ff9ff3','#2d3436','#ffffff','#000000','#ff0000','#00aa00','#0044ff','#ff8800','#8b4513'] as $color)
                    <button class="color-button" type="button" data-color="{{ $color }}" style="background: {{ $color }};" aria-label="{{ __('Farbe :color', ['color' => $color]) }}"></button>
                @endforeach
            </div>
            <div class="color-picker-row">
                <input type="color" id="colorPicker" class="color-picker" value="#ff6b6b" aria-label="{{ __('Beliebige Farbe wählen') }}">
                <input type="text" id="colorHexInput" class="color-hex-input" value="#ff6b6b" maxlength="7" spellcheck="false" aria-label="{{ __('Hex-Farbcode') }}">
                <span class="active-color" id="activeColorLabel">#ff6b6b</span>
            </div>
            <div class="pixel-counter" id="pixelCounter" aria-live="polite">
                <strong id="pixelCounterValue">–</strong>
            </div>
        </div>
        <div id="cursorCoords" class="cursor-coords hidden" aria-hidden="true"></div>
        <span id="isAuthenticated" data-auth="{{ Auth::check() ? '1' : '0' }}" style="display:none;"></span>

        <footer class="site-footer">
            <div class="footer-card">
                <h4>{{ __('Impressum & Bildnachweis') }}</h4>
                <p>
                    <strong>{{ __('Urheber- und Lizenznennung:') }}</strong>
                    <em>Official Presidential Portrait of President Donald J. Trump (2025)</em>.
                    {{ __('Quelle:') }} White House / Wikimedia Commons.
                    {{ __('Lizenz:') }} <a href="https://commons.wikimedia.org/wiki/File:Official_Presidential_Portrait_of_President_Donald_J._Trump_(2025).jpg" target="_blank" rel="noopener noreferrer">{{ __('Public domain') }}</a> {{ __('(Werk der US-Bundesregierung, Titel 17, U.S. Code § 105).') }}
                    {{ __('Weitere Details:') }} <a href="https://commons.wikimedia.org/wiki/File:Official_Presidential_Portrait_of_President_Donald_J._Trump_(2025).jpg" target="_blank" rel="noopener noreferrer">{{ __('Wikimedia Commons Datei') }}</a>.
                </p>
            </div>
            @include('legal.partials.links')
        </footer>

        <div id="cookieBanner" class="cookie-banner" role="dialog" aria-live="polite">
            <div class="cookie-content">
                <div>
                    <strong>{{ __('Cookies & Spielregeln') }}</strong>
                    <p>{{ __('Wir nutzen Cookies nur für die Sitzung und die erste Erklärung der Regeln. Deine Bestätigung bleibt lokal im Browser gespeichert.') }} <a href="{{ route('legal.privacy') }}">{{ __('Datenschutz') }}</a></p>
                </div>
                <button type="button" id="acceptCookiesBtn" class="primary-button">{{ __('Akzeptieren') }}</button>
            </div>
        </div>

        <div id="rulesModal" class="rules-modal" role="dialog" aria-modal="true" aria-labelledby="rulesTitle">
            <div class="rules-card">
                <h3 id="rulesTitle">{{ __('Spielregeln: Pixelkrieg') }}</h3>
                <ul>
                    <li><strong>{{ __('Die Wand gehört dem, der sie sich nimmt.') }}</strong> {{ __('Jeder Pixel kann von jedem überschrieben werden – auch deiner.') }}</li>
                    <li><strong>{{ __('Bau mit anderen ein Bild') }}</strong> – oder <strong>{{ __('zerstöre das Werk deiner Gegner') }}</strong> {{ __('und führe deinen eigenen Pixelkrieg. Gemeinsam oder gegeneinander: du entscheidest.') }}</li>
                    <li>{{ __('Wähle einen Pixel, färbe ihn in einer beliebigen Farbe (Farbwähler oder Hex-Code) und setze ihn.') }}</li>
                    <li>{{ __('Als Gast hast du einmalig 50 Pixel. Danach brauchst du ein kostenloses Konto (E-Mail, Google oder Facebook).') }}</li>
                    <li>{{ __('Mit Konto wächst alle 30 Sekunden ein Pixel nach – maximal 50 im Vorrat. Wer wartet, schlägt später härter zu.') }}</li>
                    <li>{{ __('Frisch gesetzte Pixel sind kurz gesperrt (10 Minuten) – verteidige, was du gebaut hast, und hol dir zurück, was dir genommen wurde.') }}</li>
                    <li>{{ __('Keine Hassbilder, keine verbotenen Symbole, keine Beleidigungen von Personen. Verstöße werden gelöscht.') }}</li>
                    <li>{{ __('Die Wall lässt sich bis 800 % zoomen – für den Feinschliff und für den Angriff auf einzelne Pixel.') }}</li>
                </ul>
                <button type="button" id="closeRulesBtn" class="primary-button">{{ __('Verstanden') }}</button>
            </div>
        </div>
    </body>
</html>
