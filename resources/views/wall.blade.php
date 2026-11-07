<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="analytics-heartbeat-url" content="{{ route('analytics.heartbeat') }}">
        <title>{{ __('PixRiot – Gemeinsam Pixelart online gestalten') }}</title>
        <meta name="description" content="{{ __('Tritt bei PixRiot einer Gruppe bei, stimmt über Pixelart-Ideen ab, gestaltet gemeinsam und veröffentlicht euer Werk auf der Community-Leinwand.') }}">
        <x-favicons />
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ __('PixRiot – baut gemeinsam ein Bild oder führt Pixelkrieg') }}">
        <meta property="og:description" content="{{ __('Schau dir diesen Ausschnitt der Pixelwand an und mach mit!') }}">
        <meta property="og:url" content="{{ url()->full() }}">
        <x-locale-head />
        <x-i18n-script />
        <meta property="og:image" content="{{ ! empty($shareImage) ? $shareImage : asset('images/og-image.png') }}">
        <meta name="twitter:card" content="summary_large_image">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="wall-page">
        <div class="pixel-shell wall-shell">
            @include('partials.site-menu', ['onWall' => true])

            <main class="canvas-stage">
                @if ($reserveProject)
                    <section class="wall-reservation-mode" id="communityReservationMode"
                        aria-labelledby="communityReservationTitle"
                        data-project-width="{{ $reserveProject->width }}"
                        data-project-height="{{ $reserveProject->height }}">
                        <div class="wall-reservation-copy">
                            <h2 id="communityReservationTitle">{{ __('Projektfläche platzieren: :title', ['title' => $reserveProject->title]) }}</h2>
                            <span id="communityReservationInstructions">{{ __('Rahmen :width × :height px – klicke auf die Wall oder nutze die Pfeiltasten, um die linke obere Ecke zu wählen. Mit Umschalt + Pfeiltaste bewegst du den Rahmen 10 Pixel. Enter oder Leertaste bestätigt die Position. Bereits belegte Projektflächen sind nicht auswählbar.', ['width' => $reserveProject->width, 'height' => $reserveProject->height]) }}</span>
                            <span id="communityReservationStatus" role="status" aria-live="polite">{{ __('Lade reservierte Flächen …') }}</span>
                            <p>{{ __('Vorhandene Pixel sind erlaubt. Die gesamte Fläche muss beim Bestätigen frei von Pixelsperren und Projektreservierungen sein. Danach ist sie für eure Gruppe geschützt.') }}</p>
                        </div>
                        <form method="POST" action="{{ route('community.projects.reserve', $reserveProject) }}" id="communityReservationForm">
                            @csrf
                            <input type="hidden" name="x" id="communityReservationX">
                            <input type="hidden" name="y" id="communityReservationY">
                            <button class="primary-button" type="submit" id="communityReservationSubmit" disabled>{{ __('Projektfläche wählen und starten') }}</button>
                            <button class="secondary-button" type="button" id="communityReservationRetry" hidden>{{ __('Reservierungen erneut laden') }}</button>
                            <a class="secondary-button" href="{{ route('community.projects.show', $reserveProject) }}">{{ __('Abbrechen') }}</a>
                        </form>
                    </section>
                @endif
                @unless ($reserveProject)
                    <div class="wall-community-hints">
                        <aside class="wall-community-promo" aria-label="{{ __('Community-Projekte') }}" data-community-group-hint x-data="{ open: true }" x-show="open">
                            <span class="wall-community-promo-icon" aria-hidden="true"><x-icon name="community" /></span>
                            <span class="wall-community-promo-copy">
                                <strong>{{ $communityGroup ? __('Deine Gruppe') : __('Gemeinsam ist mehr drin.') }}</strong>
                                <small>{{ $communityGroup ? __('Gemeinsam Ideen entwickeln und Projekte gestalten.') : __('Tritt einer Gruppe bei, stimmt über Ideen ab und gestaltet ein eigenes Projekt.') }}</small>
                            </span>
                            <a class="primary-button" href="{{ $communityGroup ? route('community.groups.show', $communityGroup) : route('community.index') }}">{{ $communityGroup?->name ?? __('Jetzt Gruppen entdecken') }} <span aria-hidden="true">→</span></a>
                            <button type="button" class="wall-community-dismiss" @click="open = false" aria-label="{{ __('Gruppenhinweis schließen') }}">×</button>
                        </aside>
                        @if ($communityProjects->isNotEmpty())
                            <aside class="wall-community-promo wall-community-projects" aria-label="{{ __('Deine laufenden Projekte') }}" data-community-project-hint x-data="{ open: true }" x-show="open">
                                <div class="wall-community-project-list">
                                    @foreach ($communityProjects as $communityProject)
                                        @php
                                            $projectAction = match ($communityProject->status) {
                                                'recruiting' => 'Teilnehmende gesucht',
                                                'scheduled' => 'Geplantes Projekt ansehen',
                                                'active' => 'Im Projekt weiterpixeln',
                                            };
                                        @endphp
                                        <a class="wall-community-project-link" href="{{ route('community.projects.show', $communityProject) }}">
                                            <span class="wall-community-promo-copy"><strong>{{ $communityProject->title }}</strong><small>{{ __(App\Models\CommunityProject::STATUS_LABELS[$communityProject->status]) }}</small></span>
                                            <span class="wall-community-project-action">{{ __($projectAction) }} <span aria-hidden="true">→</span></span>
                                        </a>
                                    @endforeach
                                </div>
                                <button type="button" class="wall-community-dismiss" @click="open = false" aria-label="{{ __('Projekthinweis schließen') }}">×</button>
                            </aside>
                        @endif
                    </div>
                @endunless
                <div class="canvas-shell">
                    <div id="canvasWorld" class="canvas-world">
                        <div id="wallBackground" class="wall-background" aria-hidden="true"></div>
                        <canvas id="pixelCanvas" width="1024" height="1024" aria-label="{{ __('Pixel-Wand') }}" @if ($reserveProject) tabindex="0" aria-describedby="communityReservationInstructions communityReservationStatus" @else tabindex="-1" @endif></canvas>
                        <div id="communityReservationOverlays" class="community-reservation-overlays" aria-hidden="true"></div>
                    </div>
                    <div class="wall-extension-controls" aria-label="{{ __('Pixelwandnavigation') }}">
                        <button type="button" class="secondary-button wall-nav wall-nav-up" id="navigateUpBtn" aria-label="{{ __('300 Pixel nach oben') }}">↑</button>
                        <button type="button" class="secondary-button wall-nav wall-nav-right" id="navigateRightBtn" aria-label="{{ __('300 Pixel nach rechts') }}">→</button>
                        <button type="button" class="secondary-button wall-nav wall-nav-down" id="navigateDownBtn" aria-label="{{ __('300 Pixel nach unten') }}">↓</button>
                        <button type="button" class="secondary-button wall-nav wall-nav-left" id="navigateLeftBtn" aria-label="{{ __('300 Pixel nach links') }}">←</button>
                        <button type="button" class="wall-extension-zone wall-extension-zone-right hidden" id="extendWallRightBtn" aria-label="{{ __('Leinwand erweitern: nach rechts') }}">
                            <span class="wall-extension-plus" aria-hidden="true">+</span>
                            <span class="wall-extension-label"><strong>{{ __('Leinwand erweitern') }}</strong><small>{{ __('Leinwand nach rechts erweitern – für alle') }}</small></span>
                        </button>
                        <button type="button" class="wall-extension-zone wall-extension-zone-bottom hidden" id="extendWallDownBtn" aria-label="{{ __('Leinwand erweitern: nach unten') }}">
                            <span class="wall-extension-plus" aria-hidden="true">+</span>
                            <span class="wall-extension-label"><strong>{{ __('Leinwand erweitern') }}</strong><small>{{ __('Leinwand nach unten erweitern – für alle') }}</small></span>
                        </button>
                        <div class="wall-zoom-controls" role="group" aria-label="{{ __('Leinwandzoom') }}">
                            <button type="button" class="secondary-button" id="zoomOutBtn" aria-label="{{ __('Verkleinern') }}" disabled><span class="zoom-sign">−</span></button>
                            <button type="button" class="secondary-button" id="zoomResetBtn" aria-label="{{ __('Zoom auf 100 Prozent zurücksetzen') }}">100%</button>
                            <button type="button" class="secondary-button" id="zoomInBtn" aria-label="{{ __('Vergrößern') }}"><span class="zoom-sign">+</span></button>
                            <button type="button" class="secondary-button wall-reload-btn" id="wallReloadBtn" data-label="{{ __('Hintergrundbild neu laden') }}" data-label-new="{{ __('Neues Hintergrundbild bzw. neue Leinwand verfügbar – klicken zum Neuladen') }}" aria-label="{{ __('Hintergrundbild neu laden') }}">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                                <span class="wall-reload-badge hidden" aria-hidden="true">!</span>
                                <span class="wall-reload-tip" role="tooltip">{{ __('Hintergrundbild neu laden') }}</span>
                            </button>
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
        <div id="communityReservationTooltip" class="community-reservation-tooltip hidden" role="tooltip"></div>
        <div id="pixelModeNotices" class="pixel-mode-notices" aria-hidden="true"></div>
        <div id="pixelModeModal" class="pixel-mode-modal hidden" role="dialog" aria-modal="true" aria-labelledby="pixelModeTitle" aria-describedby="pixelModeDescription">
            <section class="pixel-mode-card">
                <h2 id="pixelModeTitle">{{ __('Pixelmodus starten?') }}</h2>
                <p id="pixelModeDescription">{{ __('Im Pixelmodus setzt du beim Anklicken direkt ein Pixel in der ausgewählten Farbe. Du kannst jederzeit wieder in den Ansichtsmodus wechseln.') }}</p>
                <label class="pixel-mode-dismiss-option">
                    <input type="checkbox" id="pixelModeDismissPermanently">
                    <span>{{ __('Diesen Hinweis dauerhaft ausblenden') }}</span>
                </label>
                <div class="pixel-mode-actions">
                    <button type="button" class="primary-button" id="startPixelModeBtn">{{ __('Pixelmodus starten') }}</button>
                    <button type="button" class="secondary-button" id="cancelPixelModeBtn">{{ __('Weiter ansehen') }}</button>
                </div>
            </section>
        </div>

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
                    <button type="button" class="primary-button" id="limitBuyBtn">{{ __('250 Extra-Pixel kaufen') }}</button>
                </div>
                <button type="button" class="secondary-button limit-close" id="limitCloseBtn">{{ __('Okay, ich warte') }}</button>
            </div>
        </div>

        <div id="shopModal" class="shop-modal hidden" role="dialog" aria-modal="true" aria-labelledby="shopTitle">
            <div class="shop-card">
                <div class="editor-header">
                    <h3 id="shopTitle"><span class="brand-wordmark">Pix<span>Riot</span></span> · {{ __('Pixel kaufen') }}</h3>
                    <button type="button" id="closeShopBtn" class="icon-button" aria-label="{{ __('Shop schließen') }}">×</button>
                </div>
                <p class="shop-credits" id="shopCredits"></p>
                <p class="shop-note shop-explain">{{ __('So werden Pixel verbraucht: Zuerst dein kostenloser Vorrat (wächst alle 30 Sekunden um 1 nach, maximal 50), danach deine gekauften Extra-Pixel. Extra-Pixel verfallen nicht und gibt es maximal :max pro Konto.', ['max' => number_format((int) config('payments.limits.max_credits'), 0, ',', '.')]) }} <a href="{{ route('faq') }}" target="_blank" rel="noopener">{{ __('Mehr in den FAQ') }}</a></p>
                <hr class="shop-divider">
                <h4 class="shop-section-title">{{ __('Pixel kaufen') }}</h4>
                <div class="shop-products" id="shopProducts"></div>
                <hr class="shop-divider">
                <h4 class="shop-section-title" id="shopExtensionTitle">{{ __('Leinwanderweiterung') }}</h4>
                <div class="shop-products" id="shopExtensions"></div>
                <hr class="shop-divider">
                <div class="shop-checkout hidden" id="shopCheckout">
                    <section class="shop-cart" aria-labelledby="shopCartTitle">
                        <h4 class="shop-section-title" id="shopCartTitle">{{ __('Auswahl') }}</h4>
                        <div class="shop-cart-item">
                            <span id="shopCartProduct"></span>
                            <strong id="shopCartPrice"></strong>
                        </div>
                    </section>
                    <label class="shop-withdrawal-consent" for="shopWithdrawalConsent">
                        <input id="shopWithdrawalConsent" type="checkbox" required>
                        <span>
                            {{ __('Ich habe die Widerrufsbelehrung gelesen. Ich stimme ausdrücklich zu, dass PixRiot vor Ablauf der Widerrufsfrist mit der Bereitstellung der digitalen Inhalte beginnt. Mir ist bekannt, dass mein Widerrufsrecht mit Beginn der Bereitstellung erlischt.') }}
                            <a href="{{ route('legal.terms') }}#withdrawal" target="_blank" rel="noopener">{{ __('Widerrufsbelehrung lesen') }}</a>
                        </span>
                    </label>
                    <p class="shop-note shop-vat-note">{{ __('Kleinunternehmer im Sinne von § 19 UStG: Es wird keine Umsatzsteuer berechnet oder ausgewiesen.') }}</p>
                    <hr class="shop-divider">
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
            <x-palette-pipette id="wallPalettePipette" />
            <button type="button" class="secondary-button wall-glyph-toggle" id="wallDraftToggle" aria-expanded="false" aria-controls="wallDraftPanel">{{ __('Mein Bild') }}</button>
            <button type="button" class="secondary-button wall-glyph-toggle" id="wallGlyphToggle" aria-pressed="false" aria-expanded="false" aria-controls="wallGlyphPanel">{{ __('Zeichen') }}</button>
            <div class="neighborhood">
                <canvas id="neighborhoodCanvas" width="54" height="54" aria-label="{{ __('Umgebung des Pixels unter dem Cursor') }}"></canvas>
                <span class="neighborhood-label" id="neighborhoodLabel">–</span>
            </div>
            <div class="popup-color-row" id="popupColorPalette">
                @foreach (['#ff6b6b','#ffd93d','#48dbfb','#1dd1a1','#c56cf0','#ff9ff3','#2d3436','#ffffff','#000000','#ff0000','#00aa00','#0044ff','#ff8800','#8b4513'] as $color)
                    <button class="color-button" type="button" data-color="{{ $color }}" style="background: {{ $color }};" aria-label="{{ __('Farbe :color', ['color' => $color]) }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"></button>
                @endforeach
            </div>
            <details class="game-color-settings" data-game-color-settings open>
                <summary aria-label="{{ __('Farbe und Maleinstellungen') }}"><span class="game-color-swatch" data-game-color-swatch style="background: #ff6b6b"></span></summary>
                <div class="game-color-settings-panel">
                    <div class="color-picker-row">
                        <input type="color" id="colorPicker" class="color-picker" value="#ff6b6b" aria-label="{{ __('Beliebige Farbe wählen') }}">
                        <input type="text" id="colorHexInput" class="color-hex-input" value="#ff6b6b" maxlength="7" spellcheck="false" aria-label="{{ __('Hex-Farbcode') }}">
                        <span class="active-color" id="activeColorLabel">#ff6b6b</span>
                    </div>
                    <label class="wall-drag-paint-toggle" title="{{ __('Jeder gespeicherte Pixel verbraucht einen Pixel aus deinem Vorrat. Nur im Pixelmodus verfügbar.') }}">
                        <input type="checkbox" role="switch" id="wallDragPaint" disabled>
                        <span>{{ __('Beim Ziehen malen') }}</span>
                    </label>
                </div>
            </details>
            <div class="pixel-counter" id="pixelCounter" aria-live="polite">
                <strong id="pixelCounterValue">0/50</strong>
                <small id="pixelCounterExtra" class="hidden" title="{{ __('Gekaufte Extra-Pixel') }}"></small>
            </div>
            <button type="button" class="secondary-button wall-nav wall-nav-pixel-mode pixel-mode-toggle" id="pixelModeToggle" data-mobile-start="{{ __('Malen') }}" data-mobile-stop="{{ __('Ansehen') }}" aria-pressed="false">{{ __('Pixelmodus starten') }}</button>
        </div>
        <section id="wallGlyphPanel" class="wall-glyph-panel hidden" aria-label="{{ __('Zeichenstempel') }}">
            <div class="wall-glyph-heading">
                <strong data-glyph-summary>{{ __('Zeichenstempel') }}</strong>
                <button type="button" class="secondary-button" data-glyph-edit>{{ __('Ändern') }}</button>
                <button type="button" class="secondary-button" data-glyph-close aria-label="{{ __('Schließen') }}">×</button>
            </div>
            <p data-glyph-instructions>{{ __('Ein Zeichen auswählen, Vorschau positionieren und klicken. Jeder geänderte Schriftpixel kostet einen Pixel; der Hintergrund bleibt unverändert.') }}</p>
            <div class="wall-glyph-options">
                <label>{{ __('Schrift') }}<select data-glyph-font><option value="classic">{{ __('Klassisch') }}</option><option value="narrow">{{ __('Schmal') }}</option><option value="slanted">{{ __('Schräg') }}</option></select></label>
                <label>{{ __('Höhe') }}<select data-glyph-size>@foreach ($glyphCatalog['sizes'] as $size)<option value="{{ $size }}">{{ $size }} px</option>@endforeach</select></label>
            </div>
            <div class="wall-glyph-letters" data-glyph-letters role="group" aria-label="{{ __('Buchstabe oder Ziffer') }}"></div>
            <p data-glyph-status role="status"></p>
        </section>
        <script type="application/json" id="wallGlyphCatalog">@json($glyphCatalog)</script>
        @include('partials.wall-draft')
        <div id="cursorCoords" class="cursor-coords hidden" aria-hidden="true">
            <span id="cursorCoordsText"></span>
            <hr id="cursorCoordsDivider" class="hidden">
            <div id="pixelLockTooltip" class="pixel-lock-tooltip hidden" role="status" aria-live="polite"></div>
        </div>
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

        <div id="welcomeOverlay" class="welcome-overlay visible" role="dialog" aria-modal="true" aria-labelledby="welcomeTitle" aria-describedby="welcomeDescription">
            <section class="welcome-banner">
                <span class="community-promo-kicker"><x-icon name="community" />{{ __('Nicht nur Pixel setzen. Gemeinsam etwas schaffen.') }}</span>
                <h1 id="welcomeTitle">{{ __('Deine Pixel. Eure Gruppe. Ein gemeinsames Werk.') }}</h1>
                <p id="welcomeDescription">{{ __('Tritt bei PixRiot einer Gruppe bei, stimmt über Pixelart-Ideen ab, gestaltet gemeinsam und veröffentlicht euer Werk auf der Community-Leinwand.') }}</p>
                <ol class="community-onboarding-steps">
                    <li><span class="community-step-icon"><x-icon name="community" /></span><span><strong>{{ __('Gruppe finden') }}</strong><small>{{ __('Tritt einer Community-Gruppe bei.') }}</small></span></li>
                    <li><span class="community-step-icon"><x-icon name="community" /></span><span><strong>{{ __('Für Projekt anmelden') }}</strong><small>{{ __('Melde dich direkt auf der Gruppenseite an. Sobald genug Mitglieder dabei sind, startet das Projekt sofort oder zum geplanten Zeitpunkt.') }}</small></span></li>
                    <li><span class="community-step-icon"><x-icon name="wall" /></span><span><strong>{{ __('Zusammen pixeln') }}</strong><small>{{ __('Gestaltet privat – veröffentlicht euer Werk gemeinsam.') }}</small></span></li>
                </ol>
                <div class="community-onboarding-actions">
                    <button type="button" id="startGameBtn" class="primary-button">{{ __('Spiel starten') }}</button>
                    <a href="{{ route('community.index') }}" class="secondary-button">{{ __('Community entdecken') }} <span aria-hidden="true">→</span></a>
                </div>
                <a class="onboarding-privacy" href="{{ route('legal.privacy') }}">{{ __('Datenschutz') }}</a>
            </section>
        </div>

        <div id="cookieBanner" class="cookie-banner" role="dialog" aria-live="polite">
            <div class="cookie-content">
                <div>
                    <strong>{{ __('Cookies & lokaler Speicher') }}</strong>
                    <p>{{ __('Notwendige Cookies ermöglichen Sitzung und Sicherheit. Mit „Akzeptieren“ erlaubst du zusätzlich, Einführung und bestätigte Spielregeln dauerhaft in deinem Browser zu merken. Ohne Zustimmung kannst du spielen; beim nächsten Aufruf erscheint die Einführung erneut. PixRiot misst Besuche und gesetzte Pixel mit einer täglich wechselnden Hash-Kennung; Details stehen im Datenschutz. Keine externen Werbe- oder Analysedienste.') }} <a href="{{ route('legal.privacy') }}">{{ __('Datenschutz') }}</a></p>
                </div>
                <button type="button" id="declineStorageBtn" class="secondary-button">{{ __('Ohne Speichern weiterspielen') }}</button>
                <button type="button" id="acceptCookiesBtn" class="primary-button">{{ __('Akzeptieren') }}</button>
            </div>
        </div>

        <div id="rulesModal" class="rules-modal" role="dialog" aria-modal="true" aria-labelledby="rulesTitle">
            <div class="rules-card">
                <h3 id="rulesTitle">{{ __('Spielregeln:') }}</h3>
                <ul>
                    <li><strong>{{ __('Die Wand gehört dem, der sie sich nimmt.') }}</strong> {{ __('Jeder Pixel kann von jedem überschrieben werden – auch deiner.') }}</li>
                    <li><strong>{{ __('Bau mit anderen ein Bild') }}</strong> – oder <strong>{{ __('zerstöre das Werk deiner Gegner') }}</strong> {{ __('und führe deinen eigenen Pixelkrieg. Gemeinsam oder gegeneinander: du entscheidest.') }}</li>
                    <li>{{ __('Wähle einen Pixel, färbe ihn in einer beliebigen Farbe (Farbwähler oder Hex-Code) und setze ihn.') }}</li>
                    <li>{{ __('Als Gast hast du einmalig 50 Pixel. Danach brauchst du ein kostenloses Konto (E-Mail, Google oder Facebook).') }}</li>
                    <li>{{ __('Mit Konto wächst alle 30 Sekunden ein Pixel nach – maximal 50 im Vorrat. Wer wartet, schlägt später härter zu.') }}</li>
                    <li>{{ __('Gekaufte Extra-Pixel werden separat gespeichert und erst genutzt, wenn dein kostenloser Vorrat leer ist – so wächst der Gratis-Vorrat sofort weiter nach. Du kannst höchstens :max Extra-Pixel besitzen; Pakete, die darüber hinausgehen, sind im Shop gesperrt.', ['max' => number_format((int) config('payments.limits.max_credits'), 0, ',', '.')]) }}</li>
                    <li>{{ __('Frisch gesetzte Pixel sind 10 Minuten für andere gesperrt – du selbst kannst deinen Pixel in dieser Zeit noch ändern. Neue Pixel und Wandvergrößerungen sind für alle sofort nutzbar; im Hintergrundbild erscheinen sie aber erst nach bis zu 10 Minuten.') }}</li>
                    <li>{{ __('Keine Hassbilder, keine verbotenen Symbole, keine Beleidigungen von Personen. Verstöße werden gelöscht.') }}</li>
                    <li>{{ __('Die Wall lässt sich bis 1600 % zoomen – für den Feinschliff und für den Angriff auf einzelne Pixel.') }}</li>
                    <li>{{ __('Community-Projekte: Tritt einer Gruppe bei und gestalte mit. Vorschläge werden gemeinsam abgestimmt; das fertige Bild erscheint erst nach der Veröffentlichung auf der Wand.') }}</li>
                    <li>{{ __('Nach der Veröffentlichung bleibt die Fläche zunächst 7 Wochen für die Gruppe reserviert. Mindestens 5 Gruppenmitglieder können jeweils um weitere 7 Wochen verlängern; danach ist die Fläche wieder frei.') }}</li>
                </ul>
                <a class="rules-community-link" href="{{ route('community.index') }}"><x-icon name="community" />{{ __('Gruppen und Projekte entdecken') }}</a>
                <a class="onboarding-privacy" href="{{ route('legal.privacy') }}">{{ __('Datenschutz') }}</a>
                <button type="button" id="closeRulesBtn" class="primary-button">{{ __('Verstanden') }}</button>
            </div>
        </div>
        @include('partials.report-modal')
    </body>
</html>
