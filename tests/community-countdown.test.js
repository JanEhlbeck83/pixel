import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/js/community-project.js', import.meta.url), 'utf8');
const handler = source.slice(source.indexOf('let phaseReloadRequested = false;'), source.indexOf("const chat = document.querySelector('.community-chat');"));

function setup({ offset = 0, reload = true, count = 1, duration = 300000 } = {}) {
    const serverTime = Date.parse('2026-11-06T00:00:00Z');
    let time = serverTime + offset;
    let reloads = 0;
    const timers = new Map();
    let timerId = 0;
    const countdowns = Array.from({ length: count }, () => ({
        dataset: {
            countdownUntil: new Date(serverTime + duration).toISOString(),
            countdownNow: reload ? new Date(serverTime).toISOString() : undefined,
            countdownTemplate: reload ? ':minutes::seconds' : ':days/:hours/:minutes/:seconds',
            countdownExpired: 'Loading',
        },
        hasAttribute: () => reload,
        textContent: '',
    }));
    class Clock extends Date {
        static now() { return time; }
    }
    vm.runInNewContext(handler, {
        Date: Clock,
        document: { querySelectorAll: () => countdowns },
        window: {
            setInterval(fn) { timers.set(++timerId, fn); return timerId; },
            clearInterval(id) { timers.delete(id); },
            location: { reload() { reloads++; } },
        },
    });
    return {
        countdowns,
        timers,
        get reloads() { return reloads; },
        advance(ms) { time += ms; [...timers.values()].forEach((fn) => fn()); },
    };
}

test('scheduled-start countdown shows 05:00 and reloads exactly once at start', () => {
    const state = setup();
    assert.equal(state.countdowns[0].textContent, '05:00');
    state.advance(299001);
    assert.equal(state.countdowns[0].textContent, '00:01');
    assert.equal(state.reloads, 0);
    state.advance(999);
    assert.equal(state.countdowns[0].textContent, 'Loading');
    assert.equal(state.reloads, 1);
    assert.equal(state.timers.size, 0);
    state.advance(1000);
    assert.equal(state.reloads, 1);
});

for (const offset of [-3600000, 3600000]) {
    test(`server time corrects a device clock offset of ${offset}ms`, () => {
        const state = setup({ offset });
        assert.equal(state.countdowns[0].textContent, '05:00');
        state.advance(300000);
        assert.equal(state.reloads, 1);
    });
}

test('a background tab returning after expiry reloads without waiting another five minutes', () => {
    const state = setup();
    state.advance(600000);
    assert.equal(state.reloads, 1);
});

test('multiple expired project countdowns only reload the page once', () => {
    const state = setup({ count: 3 });
    state.advance(300000);
    assert.equal(state.reloads, 1);
    assert.equal(state.timers.size, 0);
});

test('an already expired grace countdown reloads immediately without creating an interval', () => {
    const state = setup({ duration: 0 });
    assert.equal(state.reloads, 1);
    assert.equal(state.timers.size, 0);
});

test('ordinary phase timers retain their text and do not reload', () => {
    const state = setup({ reload: false });
    assert.equal(state.countdowns[0].textContent, '0/0/5/0');
    state.advance(300000);
    assert.equal(state.countdowns[0].textContent, 'Loading');
    assert.equal(state.reloads, 0);
    assert.equal(state.timers.size, 0);
});
