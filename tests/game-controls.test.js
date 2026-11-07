import { test } from 'node:test';
import assert from 'node:assert/strict';
import { initTouchPan, initGameColorSettings } from '../resources/js/game-controls.js';

function panSetup() {
    const listeners = {};
    const moves = [];
    const taps = [];
    let finishes = 0;
    let enabled = true;
    let captured;
    const canvas = {
        addEventListener(name, callback) { listeners[name] = callback; },
        setPointerCapture(id) { captured = id; },
        hasPointerCapture(id) { return captured === id; },
        releasePointerCapture() { captured = null; },
    };
    initTouchPan(canvas, {
        enabled: () => enabled,
        move: (dx, dy) => moves.push([dx, dy]),
        finish: () => { finishes++; },
        tap: (event) => taps.push(event),
    });
    const send = (name, x, y, extra = {}) => {
        const event = {
            pointerType: 'touch', pointerId: 1, isPrimary: true,
            clientX: x, clientY: y, prevented: false, stopped: false,
            preventDefault() { this.prevented = true; },
            stopImmediatePropagation() { this.stopped = true; }, ...extra,
        };
        listeners[name](event);
        return event;
    };
    return { send, moves, taps, finishes: () => finishes, disable: () => { enabled = false; } };
}

test('touch browsing pans locally and refreshes once on release instead of painting', () => {
    const state = panSetup();
    assert.equal(state.send('pointerdown', 100, 100).stopped, true);
    state.send('pointermove', 97, 98);
    assert.equal(state.moves.length, 0);
    state.send('pointermove', 80, 90);
    state.send('pointermove', 60, 75);
    assert.deepEqual(state.moves, [[-20, -10], [-20, -15]]);
    assert.equal(state.finishes(), 0);
    state.send('pointerup', 60, 75);
    assert.equal(state.finishes(), 1);
    assert.equal(state.taps.length, 0);
});

test('a short touch retains tap behavior and mouse or active tools are not intercepted', () => {
    const state = panSetup();
    assert.equal(state.send('pointerdown', 10, 10, { pointerType: 'mouse' }).stopped, false);
    state.send('pointerdown', 100, 100);
    state.send('pointermove', 102, 102);
    state.send('pointerup', 102, 102);
    assert.equal(state.taps.length, 1);
    assert.equal(state.finishes(), 0);
    state.disable();
    assert.equal(state.send('pointerdown', 10, 10).stopped, false);
});

test('cancelled gestures never trigger a tap and lost capture cannot leave a stuck pan', () => {
    const state = panSetup();
    state.send('pointerdown', 10, 10);
    state.send('pointercancel', 10, 10);
    assert.equal(state.taps.length, 0);
    state.send('pointerdown', 10, 10);
    state.send('pointermove', 40, 30);
    state.send('lostpointercapture', 40, 30);
    state.send('pointerup', 40, 30);
    assert.equal(state.finishes(), 1);
    assert.equal(state.taps.length, 0);
});

test('a second finger cannot replace a captured view gesture', () => {
    const state = panSetup();
    state.send('pointerdown', 100, 100);
    assert.equal(state.send('pointerdown', 50, 50, { pointerId: 2, isPrimary: false }).stopped, true);
    state.send('pointermove', 10, 10, { pointerId: 2 });
    assert.equal(state.moves.length, 0);
    state.send('pointermove', 80, 90);
    state.send('pointerup', 80, 90);
    assert.deepEqual(state.moves, [[-20, -10]]);
    assert.equal(state.finishes(), 1);
});

test('color settings collapse on mobile, retain desktop fields, and close with focus return', () => {
    const listeners = {};
    const summary = { focus() { this.focused = true; } };
    const child = {};
    const settings = {
        open: true,
        querySelector: () => summary,
        contains: (element) => element === child || element === summary,
        addEventListener(name, callback) { listeners[name] = callback; },
    };
    const media = { matches: true, addEventListener(name, callback) { this.change = callback; } };
    const documentEvents = {};
    const oldDocument = globalThis.document;
    const oldWindow = globalThis.window;
    globalThis.document = {
        querySelectorAll: () => [settings],
        addEventListener(name, callback) { documentEvents[name] = callback; },
    };
    globalThis.window = { matchMedia: () => media };
    try {
        initGameColorSettings();
        assert.equal(settings.open, false);
        settings.open = true;
        documentEvents.click({ target: child });
        assert.equal(settings.open, true);
        documentEvents.click({ target: {} });
        assert.equal(settings.open, false);
        settings.open = true;
        listeners.keydown({ key: 'Escape', preventDefault() {}, stopPropagation() {} });
        assert.equal(summary.focused, true);
        assert.equal(settings.open, false);
        settings.open = true;
        const event = {
            target: { closest: () => ({}) }, prevented: false, stopped: false,
            preventDefault() { this.prevented = true; },
            stopImmediatePropagation() { this.stopped = true; },
        };
        documentEvents.pointerdown(event);
        assert.equal(settings.open, false);
        assert.equal(event.prevented, true);
        assert.equal(event.stopped, true);
        media.matches = false;
        media.change();
        assert.equal(settings.open, true);
        documentEvents.click({ target: {} });
        assert.equal(settings.open, true);
    } finally {
        globalThis.document = oldDocument;
        globalThis.window = oldWindow;
    }
});
