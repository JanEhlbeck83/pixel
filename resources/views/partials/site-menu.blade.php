@php($onWall = $onWall ?? false)
<header class="pixel-topbar minimal wall-menu">
    @if (auth()->user()?->isAdmin())
        <div class="admin-menu" id="adminMenu">
            <button type="button" class="wall-menu-toggle secondary-button" id="adminMenuToggle" aria-expanded="false" aria-controls="adminMenuPanel">
                <span class="wall-menu-icon" aria-hidden="true"><span></span><span></span><span></span></span>
                <span>Admin</span>
            </button>
            <div class="wall-menu-panel admin-menu-panel hidden" id="adminMenuPanel">
                <div class="brand">
                    <strong class="brand-wordmark">Pix<span>Riot</span></strong>
                </div>
                <nav class="top-actions wall-menu-links" aria-label="Admin">
                    <a href="{{ route('admin.analytics') }}"><x-icon name="faq" />{{ __('Analyse') }}</a>
                    <a href="{{ route('admin.reports') }}"><x-icon name="flag" />{{ __('Meldungen') }}</a>
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
        <div class="brand">
            <strong class="brand-wordmark">Pix<span>Riot</span></strong>
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
                <button type="button" id="openShopBtn"><x-icon name="shop" />{{ __('Pixel kaufen') }}</button>
                <button type="button" id="openExtensionShopBtn"><x-icon name="shop" />{{ __('Erweiterung kaufen') }}</button>
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
            @if ($onWall)
                <button type="button" id="openRulesBtn"><x-icon name="rules" />{{ __('Spielregeln') }}</button>
                <button type="button" id="reportAreaBtn"><x-icon name="flag" />{{ __('Inhalt melden') }}</button>
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
@if (auth()->user()?->isAdmin())
    <script>
        (() => {
            const admin = document.getElementById('adminMenu');
            const toggle = document.getElementById('adminMenuToggle');
            const panel = document.getElementById('adminMenuPanel');
            const mainToggle = document.getElementById('wallMenuToggle');
            const mainPanel = document.getElementById('wallMenuPanel');
            const closeAdmin = () => {
                panel.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
            };
            const closeMain = () => {
                mainPanel.classList.add('hidden');
                mainToggle.setAttribute('aria-expanded', 'false');
            };
            toggle.addEventListener('click', () => {
                const open = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', String(!open));
                panel.classList.toggle('hidden', open);
                if (!open) closeMain();
            });
            mainToggle.addEventListener('click', closeAdmin);
            document.addEventListener('click', (event) => { if (!admin.contains(event.target)) closeAdmin(); });
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeAdmin(); });
        })();
    </script>
@endif
@unless ($onWall)
    <script>
        (() => {
            const menu = document.querySelector('.wall-menu');
            const toggle = document.getElementById('wallMenuToggle');
            const panel = document.getElementById('wallMenuPanel');
            const close = () => {
                panel.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
            };
            toggle.addEventListener('click', () => {
                const open = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', String(!open));
                panel.classList.toggle('hidden', open);
            });
            document.addEventListener('click', (event) => { if (!menu.contains(event.target)) close(); });
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
        })();
    </script>
@endunless
