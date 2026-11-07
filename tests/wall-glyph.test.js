import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/js/wall-glyph.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/, '').replaceAll('export function ', 'function ');

function setup() {
    const element = (value = '') => ({
        value, dataset: {}, attributes: {}, handlers: {}, textContent: '', disabled: false,
        classList: { toggle() {} },
        setAttribute(name, val) { this.attributes[name] = val; },
        addEventListener(name, callback) { this.handlers[name] = callback; },
        append() {},
    });
    const elements = Object.fromEntries(['font', 'size', 'letters', 'status', 'close', 'edit', 'summary'].map((key) => [key, element()]));
    elements.font.value = 'classic';
    elements.size.value = '7';
    const toggle = element();
    const panel = element();
    panel.querySelector = (selector) => elements[selector.match(/data-glyph-(.*)]/)[1]];
    const buttons = [];
    const catalog = element();
    catalog.textContent = JSON.stringify({ fonts: { classic: { 7: Object.fromEntries(
        [...'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'].map((letter) => [letter, [[0, 0], [1, 0], [1, 1]]]),
    ) } } });
    const requests = [];
    const saved = [];
    const synced = [];
    const messages = [];
    const scope = {
        console: { error() {} },
        t: (key, params = {}) => Object.entries(params).reduce((text, [key, val]) => text.replaceAll(`:${key}`, val), key),
        document: {
            getElementById: (id) => ({ wallGlyphToggle: toggle, wallGlyphPanel: panel, wallGlyphCatalog: catalog })[id],
            querySelector: () => ({ content: 'fixture' }),
            addEventListener() {},
            createElement() { const button = element(); buttons.push(button); return button; },
        },
        apiFetch: async (url, options) => {
            requests.push({ url, ...JSON.parse(options.body) });
            return { ok: true, json: async () => ({ count: 3, pixels: [{ x: 1, y: 1 }], quota: { free: 47, credits: 0 } }) };
        },
    };
    vm.createContext(scope);
    vm.runInContext(source, scope);
    const options = {
        color: () => '#123456', dimensions: () => [100, 100],
        readColor: () => '#ffffff', blocked: () => false,
        render() {}, enable: () => true, quota: (value) => tool.quota(value),
        saved: (...args) => saved.push(args), sync: (pixels) => synced.push(pixels),
        status: (text) => messages.push(text),
    };
    const tool = scope.initWallGlyph(options);
    return { scope, tool, options, elements, buttons, toggle, requests, saved, synced, messages };
}

test('local preview checks every foreground pixel, exact color cost, bounds, missing data and locks', () => {
    const { scope } = setup();
    const preview = (read, blocked = () => false, origin = { x: 0, y: 0 }) =>
        scope.glyphPreview([[0, 0], [1, 0]], origin, '#ABCDEF', read, blocked, 2, 2);
    assert.equal(preview(() => '#abcdef').cost, 0);
    assert.equal(preview(() => '#ffffff').cost, 2);
    assert.equal(preview(() => null).valid, false);
    assert.equal(preview(() => '#ffffff', (x) => x === 1).valid, false);
    assert.equal(preview(() => '#ffffff', undefined, { x: 1, y: 0 }).valid, false);
});

test('all 36 choices reflect current balance; moving uses zero requests, placement uses exactly one', async () => {
    const state = setup();
    state.tool.quota({ free: 2, credits: 0 });
    assert.equal(state.buttons.length, 36);
    assert.ok(state.buttons.every((button) => button.disabled));
    state.tool.quota({ free: 2, credits: 1 });
    assert.ok(state.buttons.every((button) => !button.disabled));
    state.toggle.handlers.click();
    state.buttons[1].handlers.click();
    assert.equal(state.elements.summary.textContent, 'B · 7 px');
    state.elements.edit.handlers.click();
    assert.equal(state.elements.summary.textContent, 'Zeichenstempel');
    for (let x = 1; x < 90; x++) state.tool.move({ x, y: 1 });
    assert.equal(state.requests.length, 0);
    await state.tool.place({ x: 1, y: 1 });
    assert.equal(state.requests.length, 1);
    assert.equal(state.requests[0].cost, 3);
    assert.equal(state.requests[0].letter, 'B');
    assert.equal(state.saved.length, 1);
    assert.equal(state.tool.active(), false);
});

test('any blocked or unaffordable footprint is rejected locally with explicit status and no write', async () => {
    const state = setup();
    state.tool.quota({ free: 2, credits: 0 });
    state.toggle.handlers.click();
    await state.tool.place({ x: 1, y: 1 });
    state.tool.quota({ free: 50, credits: 0 });
    state.options.blocked = (x, y) => x === 2 && y === 2;
    await state.tool.place({ x: 1, y: 1 });
    assert.equal(state.requests.length, 0);
    assert.equal(state.messages.length, 2);
});

test('changed price synchronizes authoritative colors without retry or charging, and surfaces error', async () => {
    const state = setup();
    state.scope.apiFetch = async () => ({ ok: false, json: async () => ({
        message: 'Price changed', current_pixels: [{ x: 1, y: 1, color: '#123456' }],
    }) });
    state.tool.quota({ free: 50, credits: 0 });
    state.toggle.handlers.click();
    await state.tool.place({ x: 1, y: 1 });
    assert.equal(state.synced.length, 1);
    assert.equal(state.saved.length, 0);
    assert.equal(state.messages.at(-1), 'Price changed');
    assert.equal(state.tool.busy(), false);
});

test('in-flight placement remains busy after cancellation and cannot issue a second request', async () => {
    const state = setup();
    let finish;
    state.scope.apiFetch = () => new Promise((resolve) => { finish = resolve; });
    state.tool.quota({ free: 50, credits: 0 });
    state.toggle.handlers.click();
    const pending = state.tool.place({ x: 1, y: 1 });
    assert.equal(state.tool.busy(), true);
    assert.equal(state.elements.font.disabled, true);
    state.tool.cancel();
    state.toggle.handlers.click();
    assert.equal(state.tool.active(), false);
    await state.tool.place({ x: 2, y: 2 });
    finish({ ok: true, json: async () => ({ count: 3, pixels: [], quota: {} }) });
    await pending;
    assert.equal(state.tool.busy(), false);
});
