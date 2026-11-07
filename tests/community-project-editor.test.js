import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/js/community-project.js', import.meta.url), 'utf8');

test('project uses shared fixed Wall palette styles and its own labeled zoom toolbar', () => {
    const css = readFileSync(new URL('../resources/css/app.css', import.meta.url), 'utf8');
    const zoom = readFileSync(new URL('../resources/views/community/projects/canvas-zoom.blade.php', import.meta.url), 'utf8');
    assert.match(css, /\.color-bar\s*\{[^}]*position: fixed;[^}]*bottom: 0;/s);
    assert.doesNotMatch(css, /\.color-bar\.community-project-color-bar\s*\{/);
    assert.match(css, /body:has\(\.community-project-color-bar\)\s*\{[^}]*padding-bottom: var\(--project-toolbar-clearance\)/s);
    assert.doesNotMatch(zoom, /wall-zoom-controls/);
    assert.match(zoom, /community-canvas-zoom-label/);
    assert.match(zoom, />1600%<\/button>/);
});

test('mobile project layout reserves menu and palette space including safe areas', () => {
    const css = readFileSync(new URL('../resources/css/app.css', import.meta.url), 'utf8');
    const view = readFileSync(new URL('../resources/views/community/projects/show.blade.php', import.meta.url), 'utf8');
    assert.match(view, /class="menu-page community-project-page"/);
    assert.match(css, /\.community-project-page > \.pixel-shell\s*\{[^}]*padding-top: 56px/s);
    assert.match(css, /width: min\(400px, calc\(100% - 24px\)\)/);
    assert.match(css, /max-height: calc\(100dvh - 86px - var\(--project-toolbar-clearance\)\)/);
    assert.match(css, /\.community-project-page \.project-phase-notices\s*\{[^}]*bottom: calc\(16px \+ var\(--project-toolbar-clearance\)\)/s);
    assert.match(css, /--project-toolbar-clearance: calc\(var\(--color-bar-height\) \+ env\(safe-area-inset-bottom, 0px\)\)/);
    assert.match(css, /\.community-canvas-zoom-label\s*\{\s*flex-basis: 100%/);
});

test('both canvases start at 1600% with Wall zoom levels, limits and reset to 100%', () => {
    const start = source.indexOf("document.querySelectorAll('[data-community-zoom]')");
    const end = source.indexOf("document.querySelectorAll('.community-reference')", start);
    const canvases = [200, 200].map((width) => ({ width, height: 200, style: {} }));
    const controls = canvases.map(() => {
        const element = () => ({
            scrollLeft: 0, scrollTop: 0, clientWidth: 350, clientHeight: 400,
            dataset: { label: 'Reset; :percent percent' },
            addEventListener(name, callback) { this[name] = callback; },
            setAttribute(name, value) { this[name] = value; },
        });
        return {
            '[data-zoom-out]': element(), '[data-zoom-reset]': element(),
            '[data-zoom-in]': element(), '.community-canvas-viewport': element(),
        };
    });
    vm.runInNewContext(source.slice(start, end), {
        document: { querySelectorAll: () => canvases.map((canvas, i) => ({
            querySelector: (selector) => selector === 'canvas' ? canvas : controls[i][selector],
        })) },
    });
    for (let i = 0; i < canvases.length; i++) {
        assert.equal(canvases[i].style.width, '3200px');
        assert.equal(canvases[i].style.height, '3200px');
        const out = controls[i]['[data-zoom-out]'];
        const reset = controls[i]['[data-zoom-reset]'];
        const zoomIn = controls[i]['[data-zoom-in]'];
        assert.equal(zoomIn.disabled, true);
        assert.equal(out.disabled, false);
        assert.equal(reset.textContent, '1600%');
        out.click();
        assert.equal(reset.textContent, '1400%');
        reset.click();
        assert.equal(canvases[i].style.width, '200px');
        assert.equal(canvases[i].style.height, '200px');
        assert.equal(out.disabled, true);
        for (const percent of [150, 200, 300, 400, 600, 800, 1000, 1200, 1400, 1600]) {
            zoomIn.click();
            assert.equal(reset.textContent, `${percent}%`);
            assert.equal(reset['aria-label'], `Reset; ${percent} percent`);
        }
        zoomIn.click();
        assert.equal(reset.textContent, '1600%');
        let prevented = false;
        const viewport = controls[i]['.community-canvas-viewport'];
        viewport.wheel({ ctrlKey: false, deltaY: 1 });
        assert.equal(reset.textContent, '1600%');
        viewport.wheel({ ctrlKey: true, deltaY: 1, preventDefault() { prevented = true; } });
        assert.equal(prevented, true);
        assert.equal(reset.textContent, '1400%');
        assert.equal(canvases[i].width, 200);
        assert.equal(canvases[i].height, 200);
    }
});

function setup({ mobile = false } = {}) {
    const listeners = {};
    const fills = [];
    const context = {
        fillStyle: '', fillRect(...rect) { fills.push({ color: this.fillStyle, rect }); },
        strokeRect() {},
    };
    const viewport = { scrollLeft: 0, scrollTop: 0, clientWidth: 350, clientHeight: 400 };
    const classes = new Set();
    const canvas = {
        width: 201, height: 201,
        style: {}, getContext: () => context, classList: { add() {}, toggle(name, active) { if (active) classes.add(name); else classes.delete(name); } },
        closest: () => viewport,
        getBoundingClientRect: () => ({ left: 0, top: 0, width: 3216, height: 3216 }),
        setPointerCapture() {},
        addEventListener(name, callback) { listeners[name] = callback; },
    };
    const status = {};
    const quotaStatus = {};
    const input = { style: {}, value: '#ff6b6b', addEventListener() {} };
    const hex = { value: '#ff6b6b', addEventListener(name, callback) { this[name] = callback; } };
    const active = { style: {} };
    const balance = {};
    const colorSwatch = { style: {} };
    const mode = {
        dataset: { paintLabel: 'Paint', panLabel: 'Move', paintHelp: 'Painting uses pixels', panHelp: 'Swipe without painting' },
        setAttribute(name, value) { this[name] = value; },
        addEventListener(name, callback) { this[name] = callback; },
    };
    const media = { matches: mobile, addEventListener(name, callback) { this.change = callback; } };
    const pick = { disabled: true, addEventListener(name, callback) { this[name] = callback; }, setAttribute(name, value) { this[name] = value; } };
    const palettePick = { disabled: true, addEventListener(name, callback) { this[name] = callback; }, setAttribute(name, value) { this[name] = value; } };
    const selection = { hidden: true, dataset: { referenceLabel: 'Reference selection', canvasLabel: 'Canvas selection' }, style: { setProperty(name, value) { this[name] = value; } } };
    const events = {};
    const swatches = ['#ff6b6b', '#00aa00'].map((color) => ({
        dataset: { color },
        setAttribute(name, value) { this[name] = value; },
        addEventListener(name, callback) { this[name] = callback; },
    }));
    const editor = {
        dataset: { width: '201', height: '201', editable: '1', pixelsUrl: '/pixels', paintUrl: '/paint', quotaUrl: '/quota', quotaTemplate: ':free / :max :credits', quotaError: 'Quota unavailable' },
        querySelector(selector) {
            return { '#communityProjectCanvas': canvas, '#communityProjectStatus': status, '[data-paint-quota]': quotaStatus, '[data-paint-hex]': hex, '[data-paint-active]': active, '[data-paint-pick]': pick, '[data-paint-selection]': selection, '[data-paint-mode]': mode, '[data-paint-balance]': balance, '[data-game-color-swatch]': colorSwatch }[selector] || input;
        },
        querySelectorAll: (selector) => selector === '[data-paint-pick]' ? [pick, palettePick] : swatches,
        scrollIntoView() { this.scrolled = true; },
    };
    const requests = [];
    let quotaPoll;
    let resolvePaint;
    vm.runInNewContext(source.slice(source.indexOf('function communityCanvasPoint'), source.indexOf('function communityCanvasCursor')) + source.slice(source.indexOf("const editor = document.querySelector('.community-project-editor');")), {
        communityCanvasCursor() {},
        csrfToken: 'test',
        document: { hidden: false, querySelector: () => editor, addEventListener(name, callback) { events[name] = callback; } },
        window: { matchMedia: () => media, setInterval(fn) { quotaPoll = fn; } },
        console,
        fetch: async (url, options) => {
            if (url === '/paint') {
                requests.push(JSON.parse(options.body));
                return new Promise((resolve) => { resolvePaint = resolve; });
            }
            return { ok: true, json: async () => url === '/pixels'
                ? { pixels: [{ x: 1, y: 2, color: '#0044ff' }] } : { can_edit: true, free: 1, max_free: 50, credits: 0 } };
        },
    });
    return {
        listeners, requests, fills, status, quotaStatus, hex, active, input, swatches, pick, palettePick, selection, events, viewport, editor, mode, media, classes, balance, colorSwatch,
        poll: () => quotaPoll(),
        respond(statusCode, data) { resolvePaint({ ok: statusCode === 200, status: statusCode, json: async () => data }); },
    };
}
const flush = () => new Promise((resolve) => setImmediate(resolve));
const pointer = { clientX: 24, clientY: 40, pointerId: 1 };

test('mobile project starts in safe navigation mode and only explicit painting spends pixels', async () => {
    const state = setup({ mobile: true });
    await flush();
    assert.equal(state.classes.has('is-navigating'), true);
    assert.equal(state.mode['aria-pressed'], 'false');
    assert.equal(state.balance.textContent, '1/50');
    state.listeners.pointerdown(pointer);
    state.listeners.pointermove({ ...pointer, clientX: 56 });
    assert.equal(state.requests.length, 0);
    state.mode.click();
    assert.equal(state.mode['aria-pressed'], 'true');
    assert.equal(state.mode.textContent, 'Move');
    assert.equal(state.classes.has('is-navigating'), false);
    state.listeners.pointerdown(pointer);
    assert.equal(state.requests.length, 1);
    state.respond(200, { quota: { can_edit: true, free: 0, max_free: 50, credits: 12 } });
    await flush();
    assert.equal(state.balance.textContent, '0/50 +12');
    state.mode.click();
    state.listeners.pointermove({ ...pointer, clientX: 56 });
    assert.equal(state.requests.length, 1);
});

test('mobile sampling returns to direct painting and responsive changes stop a running stroke', async () => {
    const state = setup({ mobile: true });
    await flush();
    state.pick.click();
    state.listeners.pointerdown(pointer);
    assert.equal(state.requests.length, 0);
    assert.equal(state.input.value, '#0044ff');
    assert.equal(state.colorSwatch.style.background, '#0044ff');
    assert.equal(state.mode['aria-pressed'], 'true');
    state.listeners.pointerdown({ ...pointer, clientX: 56 });
    assert.equal(state.requests.length, 1);
    state.respond(200, { quota: { can_edit: true, free: 1, max_free: 50, credits: 0 } });
    await flush();
    state.media.change();
    state.listeners.pointermove({ ...pointer, clientX: 88 });
    state.listeners.pointerdown({ ...pointer, clientX: 88 });
    assert.equal(state.requests.length, 1);
    state.events['community-reference-pick']({ detail: { x: 1, y: 2, color: '#abcdef' } });
    assert.equal(state.mode['aria-pressed'], 'true');
    state.listeners.pointerdown(pointer);
    assert.equal(state.requests[1].color, '#abcdef');
});

test('painting at 1600% uses project coordinates and serializes requests', async () => {
    const state = setup();
    await flush();
    state.listeners.pointerdown(pointer);
    state.listeners.pointermove({ ...pointer, clientX: 56 });
    assert.equal(state.requests.length, 1);
    assert.deepEqual(state.requests[0], { x: 1, y: 2, color: '#ff6b6b' });
    state.respond(200, { quota: { can_edit: true, free: 1, max_free: 50, credits: 0 } });
    await flush();
    state.listeners.pointermove(pointer);
    assert.equal(state.requests.length, 1, 'same color on the same pixel does not spend another pixel');
    state.listeners.pointermove({ ...pointer, clientX: 56 });
    assert.equal(state.requests.length, 2);
});

test('quota rejection rolls back optimistic pixel and stops painting until refill', async () => {
    const state = setup();
    await flush();
    state.listeners.pointerdown(pointer);
    state.respond(429, { message: 'No pixels left', can_edit: false, free: 0, max_free: 50, credits: 0 });
    await flush();
    assert.equal(state.status.textContent, 'No pixels left');
    assert.equal(state.fills.at(-1).color, '#0044ff');
    state.listeners.pointermove({ ...pointer, clientX: 56 });
    assert.equal(state.requests.length, 1);
    await state.poll();
    state.listeners.pointermove({ ...pointer, clientX: 56 });
    assert.equal(state.requests.length, 2);
});

test('canvas pipette reads saved pixels without painting or spending quota and returns to painting', async () => {
    const state = setup();
    await flush();
    assert.equal(state.pick.disabled, false);
    state.pick.click();
    state.listeners.pointerdown(pointer);
    assert.equal(state.requests.length, 0);
    assert.equal(state.input.value, '#0044ff');
    assert.equal(state.selection.textContent, 'Canvas selection: X 1, Y 2 · #0044ff');
    assert.equal(state.selection.hidden, false);
    assert.equal(state.pick['aria-pressed'], 'false');
    state.listeners.pointerdown({ ...pointer, clientX: 56 });
    assert.equal(state.requests[0].color, '#0044ff');
});

test('palette eyedropper and canvas eyedropper stay synchronized and switch off after reference selection', async () => {
    const state = setup();
    await flush();
    assert.equal(state.palettePick.disabled, false);
    state.palettePick.click();
    assert.equal(state.pick['aria-pressed'], 'true');
    assert.equal(state.palettePick['aria-pressed'], 'true');
    state.listeners.pointerdown(pointer);
    assert.equal(state.requests.length, 0);
    assert.equal(state.input.value, '#0044ff');
    assert.equal(state.pick['aria-pressed'], 'false');
    assert.equal(state.palettePick['aria-pressed'], 'false');
    state.pick.click();
    assert.equal(state.palettePick['aria-pressed'], 'true');
    state.events['community-reference-pick']({ detail: { x: 1, y: 2, color: '#abcdef' } });
    assert.equal(state.palettePick['aria-pressed'], 'false');
    state.listeners.pointerdown(pointer);
    assert.equal(state.requests[0].color, '#abcdef');
});

test('reference selection stays visible, centers matching coordinates and does not paint', async () => {
    const state = setup();
    await flush();
    state.pick.click();
    assert.equal(state.pick['aria-pressed'], 'true');
    state.events['community-reference-pick']({ detail: { x: 190, y: 180, color: '#abcdef' } });
    assert.equal(state.requests.length, 0);
    assert.equal(state.selection.textContent, 'Reference selection: X 190, Y 180 · #abcdef');
    assert.equal(state.selection.style['--picked-color'], '#abcdef');
    assert.equal(state.input.value, '#abcdef');
    assert.equal(state.viewport.scrollLeft, 190.5 * 16 - 175);
    assert.equal(state.viewport.scrollTop, 180.5 * 16 - 200);
    assert.equal(state.editor.scrolled, true);
    state.listeners.pointermove({ ...pointer, clientX: 56 });
    assert.equal(state.selection.textContent, 'Reference selection: X 190, Y 180 · #abcdef');
    assert.equal(state.pick['aria-pressed'], 'false');
    state.listeners.pointerdown(pointer);
    assert.equal(state.requests.length, 1);
    assert.equal(state.requests[0].color, '#abcdef');
});

test('reference hover reports coordinates and click selects only with active pipette and opaque pixels', () => {
    const callbacks = {};
    const pick = { disabled: true, setAttribute(name, value) { this[name] = value; }, addEventListener(name, callback) { this[name] = callback; } };
    const status = {};
    let alpha = 255;
    let cursorInspect;
    const canvas = {
        width: 200, height: 200,
        classList: { toggle() {} },
        getContext: () => ({ clearRect() {}, drawImage() {}, getImageData: () => ({ data: [18, 52, 86, alpha] }) }),
        getBoundingClientRect: () => ({ left: 10, top: 20, width: 3200, height: 3200 }),
        addEventListener(name, callback) { callbacks[name] = callback; },
    };
    const statusElement = { dataset: { transparent: 'Transparent' }, querySelector: (s) => s === 'canvas' ? canvas : s === '[data-reference-pick]' ? pick : status };
    const selections = [];
    let image;
    const start = source.indexOf("document.querySelectorAll('.community-reference')");
    const end = source.indexOf("document.querySelectorAll('.community-project-preview')", start);
    vm.runInNewContext(source.slice(source.indexOf('function communityCanvasPoint'), source.indexOf('function communityCanvasCursor')) + source.slice(start, end), {
        document: { querySelectorAll: () => [statusElement], dispatchEvent: (e) => selections.push(e.detail) },
        communityCanvasCursor(c, inspect) { cursorInspect = inspect; },
        CustomEvent: class { constructor(type, data) { this.detail = data.detail; } },
        Image: class { constructor() { image = this; } },
        window: { setInterval() {} },
    });
    image.onload();
    assert.equal(pick.disabled, false);
    assert.equal(cursorInspect(199, 199), '#123456');
    assert.equal(status.textContent, 'X 199, Y 199 · #123456');
    callbacks.click({ clientX: 34, clientY: 60 });
    assert.equal(JSON.stringify(selections[0]), JSON.stringify({ x: 1, y: 2, color: '#123456' }));
    assert.equal(pick['aria-pressed'], 'false');
    callbacks.click({ clientX: 34, clientY: 60 });
    assert.equal(selections.length, 1);
    pick.click();
    alpha = 0;
    callbacks.click({ clientX: 34, clientY: 60 });
    assert.equal(selections.length, 1);
    assert.equal(status.textContent, 'X 1, Y 2 · Transparent');
    alpha = 255;
    callbacks.keydown({ key: 'ArrowRight', preventDefault() {} });
    callbacks.keydown({ key: 'Enter' });
    assert.equal(selections.length, 2);
});

test('hover tooltip stays on screen and hides on scrolling or leaving the canvas', () => {
    const listeners = {};
    const docEvents = {};
    const tooltip = { hidden: true, style: {}, offsetWidth: 180, offsetHeight: 28 };
    const canvas = {
        width: 200, height: 200,
        getBoundingClientRect: () => ({ left: 0, top: 0, width: 3200, height: 3200 }),
        addEventListener(name, callback) { listeners[name] = callback; },
    };
    const start = source.indexOf('function communityCanvasPoint');
    const end = source.indexOf("document.querySelectorAll('.community-reference')", start);
    vm.runInNewContext(source.slice(start, end) + '\ncommunityCanvasCursor(canvas, () => "#abcdef");', {
        canvas,
        document: { createElement: () => tooltip, body: { append() {} }, documentElement: { clientWidth: 320 }, addEventListener(name, callback) { docEvents[name] = callback; } },
        window: { innerHeight: 568, addEventListener() {} },
    });
    listeners.pointermove({ clientX: 310, clientY: 500, pointerType: 'mouse' });
    assert.equal(tooltip.textContent, 'X 19, Y 31 · #abcdef');
    assert.equal(tooltip.style.left, '132px');
    assert.equal(tooltip.style.top, '460px');
    assert.equal(tooltip.hidden, false);
    docEvents.scroll();
    assert.equal(tooltip.hidden, true);
    listeners.pointermove({ clientX: 24, clientY: 40, pointerType: 'touch' });
    assert.equal(tooltip.hidden, true);
});

test('Wall palette swatches, hex input and color picker stay synchronized without spending pixels', async () => {
    const state = setup();
    await flush();
    assert.equal(state.swatches[0]['aria-pressed'], 'true');
    state.swatches[1].click();
    assert.equal(state.input.value, '#00aa00');
    assert.equal(state.hex.value, '#00aa00');
    assert.equal(state.active.textContent, '#00aa00');
    assert.equal(state.active.style.background, '#00aa00');
    assert.equal(state.swatches[0]['aria-pressed'], 'false');
    assert.equal(state.swatches[1]['aria-pressed'], 'true');
    state.hex.value = 'ABCDEF';
    state.hex.input();
    assert.equal(state.input.value, '#abcdef');
    assert.equal(state.active.textContent, '#abcdef');
    state.hex.value = '#xyz';
    state.hex.input();
    assert.equal(state.input.value, '#abcdef');
    state.hex.blur();
    assert.equal(state.hex.value, '#abcdef');
    assert.equal(state.requests.length, 0);
    state.listeners.pointerdown(pointer);
    assert.equal(state.requests[0].color, '#abcdef');
});
