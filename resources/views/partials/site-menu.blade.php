@php($onWall = $onWall ?? false)
@vite('resources/js/site-menu.js')
<header class="pixel-topbar minimal wall-menu">
    <button type="button" class="site-menu-backdrop hidden" data-menu-backdrop aria-label="{{ __('Schließen') }}" tabindex="-1"></button>
    @if (auth()->user()?->isAdmin())
        <div class="admin-menu" id="adminMenu">
            <button type="button" class="wall-menu-toggle secondary-button" id="adminMenuToggle" aria-expanded="false" aria-controls="adminMenuPanel">
                <span class="wall-menu-icon" aria-hidden="true"><span></span><span></span><span></span></span>
                <span>Admin</span>
            </button>
            <div class="wall-menu-panel admin-menu-panel hidden" id="adminMenuPanel">
                <button type="button" class="icon-button mobile-menu-close" data-menu-close aria-label="{{ __('Schließen') }}">×</button>
                <div class="brand">
                    <a class="brand-wordmark" href="{{ route('home') }}" aria-label="{{ __('PixRiot – zur Wand') }}">Pix<span>Riot</span></a>
                </div>
                <nav class="top-actions wall-menu-links" aria-label="Admin">
                    <a href="{{ route('admin.analytics') }}"><x-icon name="faq" />{{ __('Analyse') }}</a>
                    <a href="{{ route('admin.reports') }}"><x-icon name="flag" />{{ __('Meldungen') }}</a>
                    <a href="{{ route('admin.community-groups') }}">{{ __('Gruppenfreigaben') }}</a>
                    <a href="{{ route('admin.newsletters') }}"><x-icon name="faq" />Newsletter</a>
                </nav>
            </div>
        </div>
    @endif
    <button type="button" class="wall-menu-toggle secondary-button" id="wallMenuToggle" aria-expanded="false" aria-controls="wallMenuPanel">
        <span class="wall-menu-icon" aria-hidden="true"><span></span><span></span><span></span></span>
        <span>{{ __('Menü') }}</span>
    </button>
    <div class="wall-menu-panel hidden" id="wallMenuPanel">
        <button type="button" class="icon-button mobile-menu-close" data-menu-close aria-label="{{ __('Schließen') }}">×</button>
        <div class="brand">
            <a class="brand-wordmark" href="{{ route('home') }}" aria-label="{{ __('PixRiot – zur Wand') }}">Pix<span>Riot</span></a>
        </div>
        <nav class="top-actions wall-menu-links" aria-label="{{ __('Hauptnavigation') }}">
            @if (Route::has('login'))
                @auth
                    <a href="{{ route('profile.edit') }}"><x-icon name="user" />{{ __('Profil') }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"><x-icon name="logout" />{{ __('Abmelden') }}</button>
                    </form>
                @else
                    <a href="{{ route('login') }}"><x-icon name="login" />{{ __('Anmelden') }}</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}"><x-icon name="signup" />{{ __('Registrieren') }}</a>
                    @endif
                @endauth
            @endif
            @if ($onWall)
                <hr class="wall-menu-divider">
                <button type="button" id="openShopBtn" data-menu-dismiss><x-icon name="shop" />{{ __('Pixel kaufen') }}</button>
                <button type="button" id="openExtensionShopBtn" data-menu-dismiss><x-icon name="shop" />{{ __('Erweiterung kaufen') }}</button>
                <hr class="wall-menu-divider">
                <button type="button" id="shareViewBtn"><x-icon name="link" />{{ __('Ausschnitt teilen') }}</button>
                <div id="shareFeedback" class="share-feedback hidden">
                    <p id="shareFeedbackText" role="status" aria-live="polite"></p>
                    <label for="shareUrlInput">{{ __('Link zum Teilen:') }}</label>
                    <input id="shareUrlInput" type="text" readonly>
                </div>
                <a id="shareFacebookLink" href="https://www.facebook.com/sharer/sharer.php" target="_blank" rel="noopener noreferrer"><x-icon name="facebook" />{{ __('Auf Facebook teilen') }}</a>
            @endif
            <hr class="wall-menu-divider">
            @unless ($onWall)
                <a href="{{ route('home') }}"><x-icon name="wall" />{{ __('Zur Wand') }}</a>
            @endunless
            <a href="{{ route('gallery') }}"><x-icon name="gallery" />{{ __('Pixelart') }}</a>
            <a class="wall-community-menu-link" href="{{ route('community.index') }}"><x-icon name="community" />{{ __('Gruppen & Community-Projekte') }}</a>
            @if ($onWall)
                <button type="button" id="openRulesBtn" data-menu-dismiss><x-icon name="rules" />{{ __('Spielregeln') }}</button>
                <button type="button" id="reportAreaBtn" data-menu-dismiss><x-icon name="flag" />{{ __('Inhalt melden') }}</button>
            @endif
            <a href="{{ route('faq') }}"><x-icon name="faq" />{{ __('FAQ') }}</a>
            <hr class="wall-menu-divider">
            <x-language-switcher class="lang-switch--down" />
            <a href="{{ route('legal.impressum') }}">{{ __('Impressum') }}</a>
        </nav>
        @if ($onWall)
            <details class="feedback-details">
                <summary>{{ __('Feedback senden') }}</summary>
                <form id="feedbackForm" class="feedback-form">
                    @csrf
                    @guest
                        <label for="feedbackEmail">{{ __('Deine E-Mail-Adresse') }}</label>
                        <input id="feedbackEmail" name="email" type="email" maxlength="255" autocomplete="email" required>
                    @endguest
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
        @endif
    </div>
</header>
