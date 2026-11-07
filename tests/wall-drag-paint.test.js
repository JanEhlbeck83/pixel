import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/js/wall.js', import.meta.url), 'utf8');
const strokeCode = source.slice(source.indexOf('const pendingPaints = new Set();'), source.indexOf('function handleCanvasHover'));
const moveCode = source.slice(source.indexOf('function handlePointerMove(event)'), source.indexOf('function handlePointerCancel(event)'));
const cancelCode = source.slice(source.indexOf('function handlePointerCancel(event)'), source.indexOf('function ', source.indexOf('function handlePointerCancel(event)') + 10));

function setup({ enabled = true, authenticated = true } = {}) {
    const calls = [];
    const locks = [];
    const errors = [];
    const handlers = {};
    const pixels = new Map();
    const toggle = { checked: enabled, addEventListener(name, callback) { handlers.change = callback; } };
    const scope = {
        pixelModeActive: true, reportMode: false, communityReservationMode: false,
        communityReservations: [], selectionDrag: false, dragOrigin: null,
        activeColor: '#123456', isAuthenticated: authenticated, pixels,
        palettePicking: false,
        glyphTool: null,
        draftTool: null,
        getReferencePixelColor: () => '#abcdef',
        setActiveColor(color) { scope.activeColor = color; },
        setPalettePicking(active) { scope.palettePicking = active; },
        wallDragPaint: toggle,
        canvas: {
            setPointerCapture(id) { this.capture = id; },
            hasPointerCapture(id) { return this.capture === id; },
            releasePointerCapture() { this.capture = null; },
            addEventListener(name, callback) { handlers[name] = callback; },
        },
        window: { addEventListener(name, callback) { handlers[name] = callback; } },
        document: { hidden: false, addEventListener(name, callback) { handlers[name] = callback; } },
        keyFor: (x, y) => `${x}:${y}`,
        getCellFromEvent: (e) => e.cell,
        getActivePixelLock: (x, y) => locks.some((cell) => cell.x === x && cell.y === y),
        renderCanvas() {}, refreshPixelLocks() {}, updatePixelLockTooltip() {},
        promptPixelMode() {}, setStatus(message) { errors.push(message); },
        showPixelModeNotice(message) { errors.push(message); },
        t: (key) => key,
        console: { error() {} },
        requestPixelAreaLock: async () => true,
        savePixel: async (x, y, color) => { calls.push({ x, y, color }); return true; },
    };
    vm.createContext(scope);
    vm.runInContext(strokeCode + moveCode + cancelCode, scope);
    const event = (x, y, extra = {}) => ({ button: 0, buttons: 1, isPrimary: true, pointerId: 7, cell: { x, y }, preventDefault() {}, ...extra });
    return {
        scope, calls, locks, errors, handlers, pixels, toggle,
        down(x, y, extra) { scope.handlePointerDown(event(x, y, extra)); },
        move(x, y, extra) { scope.handlePointerMove(event(x, y, extra)); },
        up() { scope.handlePointerUp({ pointerId: 7 }); },
    };
}
const flush = () => new Promise((resolve) => setImmediate(resolve));

test('switch is opt-in: disabled drag still paints exactly one pixel per click', async () => {
    const state = setup({ enabled: false });
    state.down(1, 1);
    await flush();
    state.move(2, 1);
    await flush();
    assert.equal(state.calls.length, 1);
});

test('draft placement routes before normal painting and busy saves block Wall writes', async () => {
    const state = setup();
    const placements = [];
    state.scope.draftTool = { active: () => true, busy: () => false, editing: () => false, place: (point) => placements.push(point) };
    state.down(1, 1);
    assert.deepEqual(placements, [{ x: 1, y: 1 }]);
    assert.equal(state.calls.length, 0);
    state.scope.draftTool.busy = () => true;
    state.down(2, 1);
    await flush();
    assert.equal(placements.length, 1);
    assert.equal(state.calls.length, 0);
});

test('Wall palette pipette reads color without painting or locking and returns to normal click', async () => {
    const state = setup({ enabled: false });
    state.scope.palettePicking = true;
    state.pixels.set('1:1', '#0044ff');
    state.down(1, 1);
    assert.equal(state.calls.length, 0);
    assert.equal(state.scope.activeColor, '#0044ff');
    assert.equal(state.scope.palettePicking, false);
    state.down(2, 1);
    await flush();
    assert.equal(state.calls[0].color, '#0044ff');
});

test('Wall pipette works on protected artwork in view mode and reports unloaded colors', () => {
    const state = setup();
    state.scope.pixelModeActive = false;
    state.scope.palettePicking = true;
    state.locks.push({ x: 1, y: 1 });
    state.scope.communityReservations.push({ x: 1, y: 1, width: 10, height: 10 });
    state.down(1, 1);
    assert.equal(state.scope.activeColor, '#abcdef');
    assert.equal(state.calls.length, 0);
    state.scope.palettePicking = true;
    state.scope.getReferencePixelColor = () => null;
    state.down(2, 1);
    assert.equal(state.scope.palettePicking, true);
    assert.equal(state.errors.length, 1);
    assert.equal(state.calls.length, 0);
});

test('drag paints new coordinates once per stroke and stops at release', async () => {
    const state = setup();
    state.down(1, 1);
    await flush();
    state.move(2, 1);
    await flush();
    state.move(1, 1);
    state.move(2, 1);
    await flush();
    assert.equal(state.calls.length, 2);
    assert.equal(state.scope.canvas.capture, 7);
    state.up();
    state.move(3, 1);
    await flush();
    assert.equal(state.calls.length, 2);
    assert.equal(state.scope.canvas.capture, null);
});

test('busy saves do not create a queue and guest painting uses the same path', async () => {
    const state = setup({ authenticated: false });
    let resolve;
    state.scope.savePixel = (x, y) => {
        state.calls.push({ x, y });
        return new Promise((done) => { resolve = done; });
    };
    state.down(1, 1);
    state.move(2, 1);
    state.move(3, 1);
    assert.equal(state.calls.length, 1);
    resolve(true);
    await flush();
    assert.equal(state.calls.length, 1);
    state.move(3, 1);
    assert.equal(state.calls.length, 2);
});

test('drag skips locked pixels and project reservations, including own-group areas', async () => {
    const state = setup();
    state.locks.push({ x: 2, y: 1 });
    state.scope.communityReservations.push({ x: 3, y: 1, width: 2, height: 2, is_member: true });
    state.down(1, 1);
    await flush();
    state.move(2, 1);
    state.move(3, 1);
    state.move(4, 1);
    state.move(5, 1);
    await flush();
    assert.deepEqual(state.calls.map(({ x }) => x), [1, 5]);
});

test('quota denial and network failures roll back and stop the stroke', async () => {
    for (const failure of [false, new Error('Offline')]) {
        const state = setup();
        state.pixels.set('1:1', '#ffffff');
        state.scope.savePixel = async () => {
            if (failure instanceof Error) throw failure;
            return failure;
        };
        state.down(1, 1);
        await flush();
        assert.equal(state.pixels.get('1:1'), '#ffffff');
        assert.equal(state.scope.canvas.capture, null);
        state.move(2, 1);
        assert.equal(state.pixels.has('2:1'), false);
        if (failure instanceof Error) assert.deepEqual(state.errors, ['Offline']);
    }
});

test('switch off, blur, hidden tab, lost capture, cancel or mode change stop painting', async () => {
    for (const stop of [
        (s) => s.handlers.change(), (s) => s.handlers.blur(),
        (s) => { s.scope.document.hidden = true; s.handlers.visibilitychange(); },
        (s) => s.handlers.lostpointercapture(),
        (s) => s.scope.handlePointerCancel({ pointerId: 7 }),
        (s) => { s.scope.pixelModeActive = false; s.move(2, 1); },
        (s) => { s.scope.reportMode = true; s.move(2, 1); },
    ]) {
        const state = setup();
        state.down(1, 1);
        await flush();
        stop(state);
        state.move(3, 1);
        await flush();
        assert.equal(state.calls.length, 1);
    }
});

test('secondary pointers and right mouse button cannot start or extend painting', async () => {
    const state = setup();
    state.down(1, 1, { button: 2 });
    state.down(1, 1, { isPrimary: false });
    assert.equal(state.calls.length, 0);
    state.down(1, 1);
    await flush();
    state.move(2, 1, { pointerId: 8 });
    assert.equal(state.calls.length, 1);
});
