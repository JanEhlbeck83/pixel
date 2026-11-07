import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/js/site-menu.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/m, '');

function setup({ mobile = true } = {}) {
    const documentEvents = {};
    const element = () => {
        const classes = new Set(['hidden']);
        return {
            events: {}, attributes: {}, textContent: 'Menu',
            classList: {
                add: (name) => classes.add(name),
                remove: (name) => classes.delete(name),
                contains: (name) => classes.has(name),
            },
            addEventListener(name, callback) { this.events[name] = callback; },
            setAttribute(name, value) { this.attributes[name] = value; },
            removeAttribute(name) { delete this.attributes[name]; },
            focus() { document.activeElement = this; },
            getClientRects: () => [{}],
        };
    };
    const backdrop = element();
    const createMenu = () => {
        const toggle = element();
        const close = element();
        const last = element();
        const panel = element();
        panel.querySelector = () => close;
        panel.querySelectorAll = () => [close, last];
        panel.contains = (target) => [panel, close, last].includes(target);
        return { toggle, panel, close, last };
    };
    const main = createMenu();
    const admin = createMenu();
    const menu = {
        querySelector: () => backdrop,
        contains: (target) => target === main.toggle || target === admin.toggle
            || main.panel.contains(target) || admin.panel.contains(target) || target === backdrop,
    };
    const media = { matches: mobile, addEventListener(name, callback) { this.change = callback; } };
    const document = {
        activeElement: null,
        querySelector: () => menu,
        getElementById: (id) => ({
            wallMenuToggle: main.toggle, wallMenuPanel: main.panel,
            adminMenuToggle: admin.toggle, adminMenuPanel: admin.panel,
        })[id],
        addEventListener(name, callback) { documentEvents[name] = callback; },
        body: { classList: element().classList },
    };
    vm.runInNewContext(source, {
        document, window: { matchMedia: () => media }, initGameColorSettings() {},
    });
    const key = (key, extra = {}) => {
        const event = { key, prevented: false, stopped: false, preventDefault() { this.prevented = true; }, stopImmediatePropagation() { this.stopped = true; }, ...extra };
        documentEvents.keydown(event);
        return event;
    };
    return { main, admin, backdrop, document, documentEvents, media, key };
}

test('one click opens mobile menu, admin is mutually exclusive, closing restores focus', () => {
    const state = setup();
    state.main.toggle.events.click();
    assert.equal(state.main.toggle.attributes['aria-expanded'], 'true');
    assert.equal(state.main.panel.attributes.role, 'dialog');
    assert.equal(state.main.panel.attributes['aria-modal'], 'true');
    assert.equal(state.document.activeElement, state.main.close);
    assert.equal(state.document.body.classList.contains('mobile-menu-open'), true);
    state.admin.toggle.events.click();
    assert.equal(state.main.panel.classList.contains('hidden'), true);
    assert.equal(state.admin.panel.classList.contains('hidden'), false);
    state.backdrop.events.click();
    assert.equal(state.document.activeElement, state.admin.toggle);
    assert.equal(state.document.body.classList.contains('mobile-menu-open'), false);
    assert.equal(state.admin.panel.attributes.role, undefined);
});

test('mobile menu traps Tab, closes with Escape and resets when changing layout', () => {
    const state = setup();
    state.main.toggle.events.click();
    assert.equal(state.key('Tab', { shiftKey: true }).prevented, true);
    assert.equal(state.document.activeElement, state.main.last);
    assert.equal(state.key('Tab').prevented, true);
    assert.equal(state.document.activeElement, state.main.close);
    assert.equal(state.key('Escape').prevented, true);
    assert.equal(state.document.activeElement, state.main.toggle);
    state.main.toggle.events.click();
    state.media.change();
    assert.equal(state.main.panel.classList.contains('hidden'), true);
});

test('desktop stays a dropdown and menu actions close before opening another surface', () => {
    const state = setup({ mobile: false });
    state.main.toggle.events.click();
    assert.equal(state.main.toggle.attributes['aria-expanded'], 'true');
    assert.equal(state.main.panel.attributes.role, undefined);
    assert.equal(state.backdrop.classList.contains('hidden'), true);
    state.main.panel.events.click({ target: { closest: () => ({}) } });
    assert.equal(state.main.toggle.attributes['aria-expanded'], 'false');
    state.main.toggle.events.click();
    state.documentEvents['site-menu-close']();
    assert.equal(state.main.panel.classList.contains('hidden'), true);
});
