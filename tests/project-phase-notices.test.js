import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/js/project-phase-notices.js', import.meta.url), 'utf8');

class Element {
    children = [];
    textContent = '';
    listeners = {};
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.children = children; }
    setAttribute() {}
    addEventListener(name, callback) { this.listeners[name] = callback; }
    remove() { this.removed = true; }
    get childElementCount() { return this.children.length; }
}

function setup(stored = null) {
    let response = { user_id: 7, projects: [] };
    let poll;
    let saved = stored;
    let calls = 0;
    const body = new Element();
    const config = { userId: 7, url: '/api/community/phase-updates', locale: 'de', heading: 'Updates', joinLink: 'Join now', projectLink: 'Project', groupLink: 'Group', dismiss: 'Close' };
    const document = {
        hidden: false,
        body,
        getElementById: () => ({ textContent: JSON.stringify(config) }),
        createElement: () => new Element(),
        addEventListener() {},
    };
    vm.runInNewContext(source, {
        document, console, AbortController,
        sessionStorage: { getItem: () => saved, setItem(key, value) { saved = value; } },
        fetch: async () => { calls++; return { ok: true, status: 200, json: async () => response }; },
        setInterval(fn) { poll = fn; },
        setTimeout() { return 1; },
        clearTimeout() {},
    });
    const flush = () => new Promise((resolve) => setImmediate(resolve));
    return {
        document,
        async update(projects) { response = { user_id: 7, projects }; await flush(); await poll(); await flush(); },
        get notices() { return body.children[0].children[0].children; },
        get saved() { return saved; },
        get calls() { return calls; },
    };
}

function project(overrides = {}) {
    return { id: 2, status: 'recruiting', message: 'Recruiting', url: '/project', group_url: '/group', recruitment_reminder: false, recruitment_message: 'Two more people needed', join_url: '/project#projectJoinForm', ...overrides };
}

test('new recruiting projects do not create premature phase notices', async () => {
    const state = setup();
    await state.update([project()]);
    assert.equal(state.notices.length, 0);
});

test('eligible recruitment reminders appear on first visit with a safe join link', async () => {
    const state = setup();
    await state.update([project({ recruitment_reminder: true })]);
    assert.equal(state.notices.length, 1);
    const notice = state.notices[0];
    assert.equal(notice.children[1].textContent, 'Two more people needed');
    assert.equal(notice.children[2].children[0].textContent, 'Join now');
    assert.equal(notice.children[2].children[0].href, '/project#projectJoinForm');
    assert.equal(notice.children[2].children[0].listeners.click, undefined);
});

test('delayed reminder arrives without a phase change and dismissal survives navigation', async () => {
    const state = setup();
    await state.update([project()]);
    await state.update([project({ recruitment_reminder: true })]);
    assert.equal(state.notices.length, 1);
    state.notices[0].children[2].children[2].listeners.click();
    assert.equal(state.notices.length, 0);
    await state.update([project({ recruitment_reminder: true })]);
    assert.equal(state.notices.length, 0);
    const nextPage = setup(state.saved);
    await nextPage.update([project({ recruitment_reminder: true })]);
    assert.equal(nextPage.notices.length, 0);
});

test('joined or sufficiently staffed projects remove reminders and still announce next phase', async () => {
    const state = setup();
    await state.update([project({ recruitment_reminder: true })]);
    await state.update([project({ recruitment_reminder: false })]);
    assert.equal(state.notices.length, 0);
    await state.update([project({ status: 'active', message: 'Project started' })]);
    assert.equal(state.notices.length, 1);
    assert.equal(state.notices[0].children[1].textContent, 'Project started');
});

test('participant counts update visible reminders without duplicating them', async () => {
    const state = setup();
    await state.update([project({ recruitment_reminder: true })]);
    await state.update([project({ recruitment_reminder: true, recruitment_message: 'One more person needed' })]);
    assert.equal(state.notices.length, 1);
    assert.equal(state.notices[0].children[1].textContent, 'One more person needed');
});

test('hidden offline tabs do not poll for reminders', async () => {
    const state = setup();
    await state.update([]);
    const calls = state.calls;
    state.document.hidden = true;
    await state.update([project({ recruitment_reminder: true })]);
    assert.equal(state.calls, calls);
    assert.equal(state.notices.length, 0);
});

test('phase changes offer group nonparticipants a join link until joining closes', async () => {
    const state = setup();
    await state.update([project({ status: 'recruiting' })]);
    await state.update([project({ status: 'scheduled', message: 'Start scheduled' })]);
    assert.equal(state.notices.length, 1);
    assert.equal(state.notices[0].children[1].textContent, 'Start scheduled');
    assert.equal(state.notices[0].children[2].children[0].textContent, 'Join now');
    assert.equal(state.notices[0].children[2].children[0].href, '/project#projectJoinForm');
    await state.update([project({ status: 'scheduled', join_url: null })]);
    assert.equal(state.notices[0].children[2].children[0].textContent, 'Project');
    assert.equal(state.notices[0].children[2].children[0].href, '/project');
});
