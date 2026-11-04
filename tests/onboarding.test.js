import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/js/onboarding.js', import.meta.url), 'utf8')
    .replace('export function', 'function');

function setup(values = {}, storageBlocked = false) {
    const handlers = new Map();
    const elements = new Map();
    const document = {
        activeElement: null,
        getElementById: (id) => elements.get(id),
        addEventListener: (event, callback) => handlers.set(event, callback),
    };
    const element = (id, tagName = 'DIV') => {
        const classes = new Set();
        const listeners = new Map();
        const node = {
            tagName, inert: false, hidden: false, isConnected: true,
            classList: {
                contains: (name) => classes.has(name),
                add: (name) => classes.add(name),
                remove: (name) => classes.delete(name),
                toggle: (name, enabled) => enabled ? classes.add(name) : classes.delete(name),
            },
            addEventListener: (event, callback) => listeners.set(event, callback),
            click: () => listeners.get('click')?.(),
            focus: () => { document.activeElement = node; },
            blur: () => { document.activeElement = document.body; },
            contains: (child) => node.querySelectorAll().includes(child),
            setAttribute: () => {},
            getBoundingClientRect: () => ({ height: 100 }),
            querySelectorAll: () => [],
        };
        elements.set(id, node);
        return node;
    };
    const welcome = element('welcomeOverlay');
    const rules = element('rulesModal');
    const cookies = element('cookieBanner');
    const start = element('startGameBtn', 'BUTTON');
    const acceptRules = element('closeRulesBtn', 'BUTTON');
    const acceptCookies = element('acceptCookiesBtn', 'BUTTON');
    const declineStorage = element('declineStorageBtn', 'BUTTON');
    const openRules = element('openRulesBtn', 'BUTTON');
    const menu = element('wallMenuToggle', 'BUTTON');
    element('wallMenuPanel');
    const wall = element('wall');
    const priorInert = element('priorInert');
    priorInert.inert = true;
    welcome.querySelectorAll = () => [start];
    rules.querySelectorAll = () => [acceptRules];
    cookies.querySelectorAll = () => [acceptCookies];
    document.body = {
        children: [welcome, rules, cookies, wall, priorInert],
        classList: element('body').classList,
        style: { setProperty: () => {} },
    };
    document.activeElement = document.body;
    const stored = new Map(Object.entries(values));
    const warnings = [];
    const context = vm.createContext({
        document,
        localStorage: {
            getItem: (key) => {
                if (storageBlocked) throw new Error('Storage blocked');
                return stored.get(key) ?? null;
            },
            setItem: (key, value) => {
                if (storageBlocked) throw new Error('Storage blocked');
                stored.set(key, value);
            },
        },
        console: { warn: (...args) => warnings.push(args) },
    });
    const onboarding = vm.runInContext(`${source}\ninitOnboarding();`, context);
    return { onboarding, elements, stored, handlers, document, warnings, welcome, rules, cookies, start, acceptRules, acceptCookies, declineStorage, openRules, menu, wall, priorInert };
}

for (const welcomeSeen of [false, true]) {
    for (const rulesSeen of [false, true]) {
        for (const cookiesAccepted of [false, true]) {
            test(`welcome=${welcomeSeen}, rules=${rulesSeen}, cookies=${cookiesAccepted}`, () => {
                const state = setup({
                    'pixriot-welcome-seen': welcomeSeen ? '1' : '0',
                    'pixriot-rules-seen': rulesSeen ? '1' : '0',
                    'pixriot-onboarding-storage-accepted': cookiesAccepted ? '1' : '0',
                });
                const effectiveWelcome = cookiesAccepted && welcomeSeen;
                const effectiveRules = cookiesAccepted && rulesSeen;
                assert.equal(state.welcome.hidden, effectiveWelcome);
                assert.equal(state.rules.hidden, !effectiveWelcome || effectiveRules);
                assert.equal(state.onboarding.isLocked(), !effectiveWelcome || !effectiveRules);
                assert.equal(state.wall.inert, state.onboarding.isLocked());
                assert.equal(state.cookies.inert, state.onboarding.isLocked());
                assert.equal(state.cookies.classList.contains('visible'), !cookiesAccepted);

                if (!effectiveWelcome) {
                    state.start.click();
                    assert.equal(state.rules.hidden, effectiveRules);
                    assert.equal(state.onboarding.isLocked(), !effectiveRules);
                    assert.equal(state.stored.get('pixriot-welcome-seen'), cookiesAccepted ? '1' : (welcomeSeen ? '1' : '0'));
                }
                if (!effectiveRules) {
                    const event = { key: 'Escape', preventDefault() {}, stopImmediatePropagation() {} };
                    state.handlers.get('keydown')(event);
                    assert.equal(state.onboarding.isLocked(), true);
                    assert.equal(state.stored.get('pixriot-rules-seen'), rulesSeen ? '1' : '0');
                    state.acceptRules.click();
                    assert.equal(state.stored.get('pixriot-rules-seen'), cookiesAccepted ? '1' : (rulesSeen ? '1' : '0'));
                }
                assert.equal(state.onboarding.isLocked(), false);
                assert.equal(state.wall.inert, false);
                assert.equal(state.cookies.inert, false);
                assert.notEqual(state.document.activeElement, state.menu);
                assert.equal(state.priorInert.inert, true);
                assert.equal(state.cookies.classList.contains('visible'), !cookiesAccepted);
                state.openRules.click();
                assert.equal(state.onboarding.isLocked(), true);
                assert.equal(state.cookies.inert, true);
                state.acceptRules.click();
                assert.equal(state.onboarding.isLocked(), false);
                assert.equal(state.cookies.inert, false);
            });
        }
    }
}

test('storage acceptance is blocked until onboarding is completed', () => {
    const state = setup();
    state.acceptCookies.click();
    assert.equal(state.onboarding.isLocked(), true);
    assert.equal(state.welcome.hidden, false);
    assert.equal(state.stored.get('pixriot-rules-seen'), undefined);
    assert.equal(state.stored.get('pixriot-welcome-seen'), undefined);
    assert.equal(state.stored.get('pixriot-onboarding-storage-accepted'), undefined);
    state.start.click();
    state.acceptRules.click();
    assert.equal(state.stored.size, 0);
    state.acceptCookies.click();
    assert.equal(state.stored.get('pixriot-welcome-seen'), '1');
    assert.equal(state.stored.get('pixriot-rules-seen'), '1');
    assert.equal(state.stored.get('pixriot-onboarding-storage-accepted'), '1');
});

test('declining storage leaves the game unlocked and stores nothing', () => {
    const state = setup();
    state.start.click();
    state.acceptRules.click();
    state.declineStorage.click();
    assert.equal(state.onboarding.isLocked(), false);
    assert.equal(state.cookies.classList.contains('visible'), false);
    assert.equal(state.stored.size, 0);
});

test('old cookie acceptance does not grant consent for the new purpose', () => {
    const state = setup({ 'pixriot-cookie-accepted': '1', 'pixriot-welcome-seen': '1', 'pixriot-rules-seen': '1' });
    assert.equal(state.welcome.hidden, false);
    assert.equal(state.cookies.classList.contains('visible'), true);
});

test('keyboard focus stays in the lock screen and excludes cookie controls', () => {
    const state = setup();
    assert.equal(state.document.activeElement, state.start);
    state.handlers.get('keydown')({ key: 'Tab', preventDefault() {} });
    assert.equal(state.document.activeElement, state.start);
    state.handlers.get('keydown')({ key: 'Tab', preventDefault() {} });
    assert.equal(state.document.activeElement, state.start);
    state.start.click();
    state.handlers.get('keydown')({ key: 'Tab', preventDefault() {} });
    assert.equal(state.document.activeElement, state.acceptRules);
});

test('blocked storage is reported and the current page can still be unlocked', () => {
    const state = setup({}, true);
    state.start.click();
    assert.equal(state.onboarding.isLocked(), true);
    state.acceptRules.click();
    assert.equal(state.onboarding.isLocked(), false);
    assert.equal(state.warnings.length, 1);
});
