export function initOnboarding() {
    const welcome = document.getElementById('welcomeOverlay');
    const rules = document.getElementById('rulesModal');
    const cookies = document.getElementById('cookieBanner');
    const start = document.getElementById('startGameBtn');
    const acceptRules = document.getElementById('closeRulesBtn');
    const openRules = document.getElementById('openRulesBtn');
    const acceptCookies = document.getElementById('acceptCookiesBtn');
    const declineStorage = document.getElementById('declineStorageBtn');
    const keys = {
        welcome: 'pixriot-welcome-seen',
        rules: 'pixriot-rules-seen',
        cookies: 'pixriot-onboarding-storage-accepted',
    };
    const read = (key) => {
        try {
            return localStorage.getItem(key) === '1';
        } catch (error) {
            console.warn('Onboarding preferences could not be read:', error);
            return false;
        }
    };
    const remember = (key) => {
        try {
            localStorage.setItem(key, '1');
        } catch (error) {
            console.warn('Onboarding preference could not be saved; it applies only to this page:', error);
        }
    };
    let storageAccepted = read(keys.cookies);
    let welcomeSeen = storageAccepted && read(keys.welcome);
    let rulesSeen = storageAccepted && read(keys.rules);
    const rememberProgress = () => {
        if (!storageAccepted) return;
        if (welcomeSeen) remember(keys.welcome);
        if (rulesSeen) remember(keys.rules);
    };
    let screen = null;
    let previousFocus = null;
    const backgrounds = [...document.body.children]
        .filter((element) => ![welcome, rules].includes(element) && element.tagName !== 'SCRIPT');
    const previousInert = new Map(backgrounds.map((element) => [element, element.inert]));

    const setScreen = (next) => {
        if (!screen && next) previousFocus = document.activeElement;
        screen = next;
        welcome.classList.toggle('visible', next === welcome);
        rules.classList.toggle('visible', next === rules);
        welcome.hidden = next !== welcome;
        rules.hidden = next !== rules;
        for (const element of backgrounds) {
            element.inert = next ? true : previousInert.get(element);
        }
        document.body.classList.toggle('onboarding-locked', Boolean(next));
        if (next) {
            (next === welcome ? start : acceptRules).focus();
        } else if (previousFocus?.isConnected && previousFocus !== document.body) {
            previousFocus.focus();
        } else if (welcome.contains(document.activeElement) || rules.contains(document.activeElement)) {
            document.activeElement.blur();
        }
    };

    cookies.classList.toggle('visible', !storageAccepted);
    setScreen(!welcomeSeen ? welcome : (!rulesSeen ? rules : null));

    start.addEventListener('click', () => {
        welcomeSeen = true;
        rememberProgress();
        setScreen(rulesSeen ? null : rules);
    });
    acceptRules.addEventListener('click', () => {
        rulesSeen = true;
        rememberProgress();
        setScreen(null);
    });
    acceptCookies.addEventListener('click', () => {
        if (screen) return;
        storageAccepted = true;
        remember(keys.cookies);
        rememberProgress();
        cookies.classList.remove('visible');
    });
    declineStorage.addEventListener('click', () => {
        if (!screen) cookies.classList.remove('visible');
    });
    openRules.addEventListener('click', () => {
        document.getElementById('wallMenuPanel').classList.add('hidden');
        document.getElementById('wallMenuToggle').setAttribute('aria-expanded', 'false');
        setScreen(rules);
    });
    document.addEventListener('keydown', (event) => {
        if (!screen) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
        if (event.key !== 'Tab') return;
        const focusable = [...screen.querySelectorAll('button, a[href]')];
        const index = focusable.indexOf(document.activeElement);
        const next = event.shiftKey
            ? (index <= 0 ? focusable.length - 1 : index - 1)
            : (index + 1) % focusable.length;
        event.preventDefault();
        focusable[next].focus();
    }, true);

    return { isLocked: () => Boolean(screen) };
}
