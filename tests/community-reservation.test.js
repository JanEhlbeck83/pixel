import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/js/wall.js', import.meta.url), 'utf8');
const start = source.indexOf('function communityReservationAreaOverlaps(');
const end = source.indexOf('\nfunction updateCommunityReservationSubmit', start);
const check = (locks, reservations, area) => vm.runInNewContext(
    `${source.slice(start, end)}; communityReservationAreaOverlaps(area);`,
    { communityReservationPixelLocks: locks, communityReservations: reservations, area, Date },
);
const area = { x: 10, y: 20, width: 200, height: 200 };

test('every locked pixel in the rectangle blocks selection including far edge', () => {
    for (const [x, y] of [[10, 20], [209, 219], [100, 100]]) {
        assert.equal(check([{ x, y, locked_until: new Date(Date.now() + 60000).toISOString() }], [], area), true);
    }
});

test('expired locks and immediately adjacent pixels do not block selection', () => {
    assert.equal(check([{ x: 10, y: 20, locked_until: new Date(Date.now() - 1).toISOString() }], [], area), false);
    assert.equal(check([{ x: 210, y: 20, locked_until: new Date(Date.now() + 60000).toISOString() }], [], area), false);
});

test('project reservations block overlap but not adjacency', () => {
    assert.equal(check([], [{ x: 209, y: 219, width: 2, height: 2 }], area), true);
    assert.equal(check([], [{ x: 210, y: 20, width: 2, height: 2 }], area), false);
});

test('project canvas is white before pixel loading and does not copy the reference', () => {
    const projectSource = readFileSync(new URL('../resources/js/community-project.js', import.meta.url), 'utf8');
    const code = projectSource.slice(projectSource.indexOf("const editor = document.querySelector('.community-project-editor');"));
    const fills = [];
    const context = {
        fillStyle: '', fillRect(...rect) { fills.push({ color: this.fillStyle, rect }); },
    };
    const canvas = { style: {}, getContext: () => context, classList: { add() {}, toggle() {} }, addEventListener() {}, closest: () => ({}) };
    const input = { style: {}, dataset: {}, setAttribute() {}, addEventListener() {} };
    const editor = {
        dataset: { width: '201', height: '201', editable: '0', pixelsUrl: '/pixels' },
        querySelector(selector) { return selector === '#communityProjectCanvas' ? canvas : input; },
        querySelectorAll: () => [],
    };
    vm.runInNewContext(code, {
        communityCanvasCursor() {},
        document: { querySelector: () => editor, addEventListener() {} },
        fetch: () => new Promise(() => {}),
        window: { matchMedia: () => ({ matches: false, addEventListener() {} }), setInterval() {} },
        console,
    });
    assert.equal(fills.length, 1);
    assert.equal(fills[0].color, '#ffffff');
    assert.deepEqual([...fills[0].rect], [0, 0, 201, 201]);
});
