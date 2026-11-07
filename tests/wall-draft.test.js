import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/js/wall-draft.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/, '').replaceAll('export function ', 'function ');

function setup(initial = {}) {
    const element = () => ({
        value: '', handlers: {}, textContent: '', disabled: false, attributes: {}, style: {}, inert: false,
        classList: { toggle() {} },
        addEventListener(name, callback) { this.handlers[name] = callback; },
        setAttribute(name, value) { this.attributes[name] = value; },
        focus() {},
    });
    const names = ['canvas', 'viewport', 'title', 'zoom', 'status', 'select', 'selector', 'save', 'place', 'erase', 'close', 'zoom-out', 'zoom-in', 'fit'];
    const controls = Object.fromEntries(names.map((name) => [name, element()]));
    const placements = { confirm: element(), back: element(), 'placement-status': element() };
    const canvas = controls.canvas;
    const drawn = [];
    canvas.getContext = () => ({ clearRect() {}, fillRect(...args) { drawn.push(args); } });
    canvas.getBoundingClientRect = () => ({ left: 10, top: 20, width: 120, height: 300 });
    canvas.setPointerCapture = () => {};
    Object.assign(controls.viewport, { clientWidth: 300, clientHeight: 324, scrollLeft: 0, scrollTop: 0 });
    controls.zoom.options = [2, 4, 6, 8, 10, 12, 16, 20].map((value) => ({ value }));
    controls.zoom.append = (option) => controls.zoom.options.push(option);
    controls.select.replaceChildren = (...children) => { controls.select.children = children; };
    const toggle = element();
    const panel = element();
    panel.querySelector = (selector) => controls[selector.match(/data-draft-(.*)]/)[1]];
    const placement = element();
    placement.querySelector = (selector) => placements[selector.match(/data-draft-(.*)]/)[1]];
    const requests = [];
    const saved = [];
    const messages = [];
    let server = { id: 1, title: '', pixels: {}, paid_cells: [], version: 0, ...initial };
    let free = 50;
    const scope = {
        console: { error() {} },
        t: (key, params = {}) => Object.entries(params).reduce((text, [key, val]) => text.replaceAll(`:${key}`, val), key),
        document: {
            getElementById: (id) => ({ wallDraftToggle: toggle, wallDraftPanel: panel, wallDraftPlacement: placement })[id],
            querySelector: () => ({ content: 'fixture' }),
            addEventListener() {},
            createElement: element,
        },
        window: { location: { hash: '' }, addEventListener() {} },
        apiFetch: async (url, options) => {
            const body = options.body ? JSON.parse(options.body) : null;
            requests.push({ url, method: options.method, body });
            let data;
            if (options.method === 'GET') data = { drafts: [server] };
            else if (options.method === 'PATCH') {
                let cost = 0;
                body.changes.forEach(({ index, color }) => {
                    if (color === null) delete server.pixels[index];
                    else {
                        server.pixels[index] = color;
                        if (!server.paid_cells.includes(index)) { server.paid_cells.push(index); cost++; }
                    }
                });
                server.title = body.title;
                server.version++;
                free -= cost;
                data = { draft: server, cost };
            } else {
                data = { pixels: [{ x: body.x, y: body.y, color: '#123456' }] };
                server = { ...server, pixels: {}, paid_cells: [], title: '', version: server.version + 1 };
                data.draft = server;
            }
            return { ok: true, json: async () => JSON.parse(JSON.stringify({ ...data, quota: { free, credits: 0 } })) };
        },
    };
    vm.createContext(scope);
    vm.runInContext(source, scope);
    const options = {
        color: () => '#123456', dimensions: () => [1000, 1000], blocked: () => false,
        render() {}, editable: () => true, exclusive() {}, enable: () => true,
        quota(value) { tool.quota(value); },
        saved(...args) { saved.push(args); }, status(text) { messages.push(text); },
    };
    const tool = scope.initWallDraft(options);
    const pointer = (x, y) => ({
        clientX: 10 + x * 6 + 1, clientY: 20 + y * 6 + 1,
        button: 0, isPrimary: true, pointerId: 7, preventDefault() {},
    });
    return {
        scope, tool, options, controls, placements, toggle, requests, saved, messages, drawn,
        async open() { toggle.handlers.click(); await flush(); },
        down(x, y) { canvas.handlers.pointerdown(pointer(x, y)); },
        move(x, y) { canvas.handlers.pointermove(pointer(x, y)); },
        up() { canvas.handlers.pointerup(); },
    };
}
const flush = () => new Promise((resolve) => setImmediate(resolve));

test('zoomed draft coordinates remain native 20 by 50 and transparent holes are not targets', () => {
    const state = setup();
    const canvas = state.controls.canvas;
    assert.deepEqual(JSON.parse(JSON.stringify(state.scope.draftPoint({ clientX: 129, clientY: 319 }, canvas))), { x: 19, y: 49, index: 999 });
    assert.equal(state.scope.draftPoint({ clientX: 130, clientY: 320 }, canvas), null);
    const check = (blocked, x = 1) => state.scope.draftPreview({ 0: '#123456', 999: '#abcdef' }, { x, y: 1 }, blocked, 30, 60);
    assert.equal(check((x, y) => x === 2 && y === 1).valid, true);
    assert.equal(check((x, y) => x === 20 && y === 50).valid, false);
    assert.equal(check(() => false, 20).valid, false);
    assert.equal(check(() => false).points.length, 2);
});

test('drawing interpolates a stroke locally and saves it in one request, paid cells can be edited with zero balance', async () => {
    const state = setup();
    await state.open();
    state.down(0, 0);
    state.move(4, 0);
    assert.equal(state.requests.length, 1);
    state.up();
    await flush();
    assert.equal(state.requests.length, 2);
    assert.equal(state.requests[1].body.changes.length, 5);
    assert.match(state.controls.status.textContent, /−5 Pixel/);
    state.tool.quota({ free: 0, credits: 0 });
    state.controls.erase.handlers.click();
    state.down(0, 0); state.up(); await flush();
    assert.match(state.controls.status.textContent, /−0 Pixel/);
    state.controls.erase.handlers.click();
    state.options.color = () => '#ffffff';
    state.down(0, 0); state.up(); await flush();
    assert.equal(state.requests.at(-1).body.changes[0].color, '#ffffff');
    state.tool.quota({ free: 0, credits: 0 });
    const count = state.requests.length;
    state.down(5, 0); state.up(); await flush();
    assert.equal(state.requests.length, count);
    assert.match(state.controls.status.textContent, /Nicht genügend Pixel/);
});

test('collapse preserves draft and zoom never sends a request or changes native dimensions', async () => {
    const state = setup({ pixels: { 999: '#abcdef' }, paid_cells: [999] });
    await state.open();
    const count = state.requests.length;
    state.controls['zoom-in'].handlers.click();
    assert.equal(state.controls.canvas.style.height, '400px');
    state.controls['zoom-out'].handlers.click();
    assert.equal(state.controls.canvas.style.height, '300px');
    state.controls.fit.handlers.click();
    state.controls.close.handlers.click();
    assert.equal(state.tool.editing(), false);
    await state.open();
    assert.equal(state.tool.editing(), true);
    assert.equal(state.requests.length, count);
});

test('placement preview uses no requests, click only pins, confirmation publishes exactly once with zero additional charge', async () => {
    const state = setup({ pixels: { 0: '#123456' }, paid_cells: [0] });
    await state.open();
    state.tool.quota({ free: 0, credits: 0 });
    await state.controls.place.handlers.click();
    assert.equal(state.tool.active(), true);
    for (let x = 0; x < 100; x++) state.tool.move({ x, y: 1 });
    state.tool.place({ x: 10, y: 10 });
    assert.equal(state.requests.length, 1);
    assert.equal(state.placements.confirm.disabled, false);
    await state.placements.confirm.handlers.click();
    assert.equal(state.requests.length, 2);
    assert.equal(state.requests[1].method, 'POST');
    assert.deepEqual(state.requests[1].body, { version: 0, x: 10, y: 10 });
    assert.equal(state.saved.length, 1);
    assert.equal(state.tool.active(), false);
    assert.equal(state.controls.place.disabled, true);
    assert.equal(state.placements.confirm.disabled, true);
});

test('local lock prevents confirmation and server rejection preserves the whole draft without retry', async () => {
    const state = setup({ pixels: { 0: '#123456' }, paid_cells: [0] });
    await state.open();
    await state.controls.place.handlers.click();
    state.options.blocked = () => true;
    state.tool.place({ x: 1, y: 1 });
    assert.equal(state.placements.confirm.disabled, true);
    await state.placements.confirm.handlers.click();
    assert.equal(state.requests.length, 1);
    state.options.blocked = () => false;
    state.tool.place({ x: 1, y: 1 });
    let attempts = 0;
    state.scope.apiFetch = async () => { attempts++; return { ok: false, json: async () => ({ message: 'Locked' }) }; };
    await state.placements.confirm.handlers.click();
    assert.equal(attempts, 1);
    assert.equal(state.tool.active(), true);
    assert.equal(state.saved.length, 0);
    assert.equal(state.placements['placement-status'].textContent, 'Locked');
});

test('quota conflict restores authoritative draft, while network errors retain unsaved edits for explicit retry', async () => {
    const state = setup();
    await state.open();
    state.scope.apiFetch = async () => { throw new Error('Offline'); };
    state.down(0, 0); state.up(); await flush();
    assert.equal(state.controls.place.disabled, true);
    assert.equal(state.controls.status.textContent, 'Offline');
    state.scope.apiFetch = async () => ({
        ok: false, json: async () => ({ message: 'Quota rejected', draft: { id: 1, title: '', pixels: {}, paid_cells: [], version: 0 } }),
    });
    await state.controls.save.handlers.click();
    assert.equal(state.controls.status.textContent, 'Quota rejected');
    assert.equal(state.controls.place.disabled, true);
    assert.equal(state.tool.busy(), false);
});

test('in-flight save prevents editing or switching tools and cannot submit twice', async () => {
    const state = setup();
    await state.open();
    let finish;
    let attempts = 0;
    state.scope.apiFetch = () => { attempts++; return new Promise((resolve) => { finish = resolve; }); };
    state.down(0, 0); state.up();
    assert.equal(state.tool.busy(), true);
    state.tool.cancel();
    state.down(1, 0); state.up();
    await state.controls.save.handlers.click();
    assert.equal(attempts, 1);
    finish({ ok: true, json: async () => ({ draft: { id: 1, title: '', pixels: { 0: '#123456' }, paid_cells: [0], version: 1 }, cost: 1 }) });
    await flush();
    assert.equal(state.tool.busy(), false);
});
