<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Pixel Chaos</title>
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
                                <a href="{{ route('dashboard') }}">Dashboard</a>
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
                        <button type="button" class="wall-extension-zone wall-extension-zone-right hidden" id="extendWallRightBtn" aria-label="Pixelwand nach rechts erweitern">+</button>
                        <button type="button" class="wall-extension-zone wall-extension-zone-bottom hidden" id="extendWallDownBtn" aria-label="Pixelwand nach unten erweitern">+</button>
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

        <div id="editorPopup" class="editor-popup hidden" aria-modal="true" role="dialog">
            <div class="editor-card">
                <div class="editor-header">
                    <h3>Gestaltungsfläche bearbeiten</h3>
                    <button type="button" id="closeEditorBtn" class="icon-button" aria-label="Popup schließen">×</button>
                </div>

                <div class="paint-toolbar">
                    <span class="tool-label">Werkzeug</span>
                    <div class="popup-color-row" id="popupColorPalette">
                        @foreach (['#ff6b6b','#ffd93d','#48dbfb','#1dd1a1','#c56cf0','#ff9ff3','#2d3436','#ffffff'] as $color)
                            <button class="color-button" type="button" data-color="{{ $color }}" style="background: {{ $color }};" aria-label="Farbe {{ $color }}"></button>
                        @endforeach
                    </div>
                    <div class="current-color-row">
                        <span class="active-color" id="activeColorLabel">#ff6b6b</span>
                    </div>
                </div>

                <div class="crop-preview-wrap">
                    <div class="selection-preview-panel editor-panel">
                        <div class="preview-label">Bearbeiten</div>
                        <canvas id="selectionPreviewCanvas" width="240" height="240" aria-label="Bearbeitungsbereich"></canvas>
                    </div>
                    <div class="selection-preview-panel reference-panel">
                        <div class="preview-label">Original</div>
                        <canvas id="selectionOriginalCanvas" width="240" height="240" aria-label="Originalbereich"></canvas>
                    </div>
                </div>

                <label class="upload-box wide">
                    Bild in die Auswahl laden
                    <input type="file" id="imageUpload" accept="image/*">
                </label>

                <div class="popup-actions">
                    <button type="button" id="applySelectionBtn" class="primary-button">Übernehmen</button>
                    <button type="button" id="clearSelectionBtn" class="secondary-button">Abbrechen</button>
                </div>
            </div>
        </div>

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
                    <p>Wir nutzen Cookies nur für die Sitzung und die erste Erklärung der Regeln. Deine Auswahl bleibt lokal im Browser gespeichert.</p>
                </div>
                <button type="button" id="acceptCookiesBtn" class="primary-button">Akzeptieren</button>
            </div>
        </div>

        <div id="rulesModal" class="rules-modal" role="dialog" aria-modal="true" aria-labelledby="rulesTitle">
            <div class="rules-card">
                <h3 id="rulesTitle">So funktioniert Pixel Chaos</h3>
                <ul>
                    <li>Jede Fläche beginnt als freie Leinwand.</li>
                    <li>Mit einem Klick und Drag legst du eine Gestaltungsfläche mit Mindestgröße 5×5 px fest.</li>
                    <li>Du kannst die Fläche beliebig erweitern oder neu positionieren.</li>
                    <li>Im Editor färbst du einzelne Pixel per Klick.</li>
                    <li>Alternativ kannst du ein Bild hochladen. Der Ausschnitt wird automatisch auf die gekaufte Fläche zugeschnitten.</li>
                    <li>Die Wall lädt Bildausschnitte in 300-px-Schritten und lässt sich rechts und unten erweitern.</li>
                    <li>Logge dich ein, um deine Änderungen dauerhaft zu speichern.</li>
                </ul>
                <button type="button" id="closeRulesBtn" class="primary-button">Verstanden</button>
            </div>
        </div>
    </body>
</html>
