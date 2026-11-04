import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/js/app.js', import.meta.url), 'utf8');
const handler = source.slice(source.indexOf("shareViewBtn?.addEventListener('click'"), source.indexOf("document.getElementById('shareUrlInput')?.addEventListener"));

function setup(navigator) {
    let click;
    const feedback = { hidden: true, classList: { add() { feedback.hidden = true; }, remove() { feedback.hidden = false; } } };
    const message = { textContent: '' };
    const input = { value: '', focused: false, selected: false, focus() { this.focused = true; }, select() { this.selected = true; } };
    const button = { disabled: false, addEventListener(event, fn) { click = fn; } };
    const url = 'https://pixriot.online/es?x=1800&y=900&z=4';
    vm.runInNewContext(handler, {
        shareViewBtn: button, navigator, buildShareUrl: () => url, t: (text) => text,
        document: { getElementById: (id) => ({ shareFeedback: feedback, shareFeedbackText: message, shareUrlInput: input })[id] },
        console: { warn() {} },
    });
    return { click, feedback, message, input, button, url };
}

test('clipboard success shows the confirmation and exact localized view URL', async () => {
    let copied;
    const state = setup({ clipboard: { async writeText(url) { copied = url; } } });
    await state.click();
    assert.equal(copied, state.url);
    assert.equal(state.input.value, state.url);
    assert.equal(state.message.textContent, 'Link kopiert!');
    assert.equal(state.feedback.hidden, false);
    assert.equal(state.button.disabled, false);
});

test('native share success is visible and does not copy', async () => {
    let data;
    const state = setup({ async share(value) { data = value; } });
    await state.click();
    assert.equal(data.url, state.url);
    assert.equal(data.text, 'Schau dir diesen Ausschnitt an!');
    assert.equal(state.feedback.hidden, false);
    assert.equal(state.message.textContent, 'Ausschnitt geteilt.');
});

test('cancelled native sharing does not copy or show stale success', async () => {
    const state = setup({
        async share() { throw Object.assign(new Error('Cancelled'), { name: 'AbortError' }); },
        clipboard: { writeText() { assert.fail('Must not copy'); } },
    });
    await state.click();
    assert.equal(state.feedback.hidden, true);
    assert.equal(state.button.disabled, false);
});

test('native failure falls back to clipboard', async () => {
    const state = setup({ async share() { throw new Error('Denied'); }, clipboard: { async writeText() {} } });
    await state.click();
    assert.equal(state.message.textContent, 'Link kopiert!');
});

for (const navigator of [{}, { clipboard: { async writeText() { throw new Error('Denied'); } } }]) {
    test('unavailable or denied clipboard shows a selected manual-copy field', async () => {
        const state = setup(navigator);
        await state.click();
        assert.equal(state.feedback.hidden, false);
        assert.equal(state.input.value, state.url);
        assert.equal(state.input.focused, true);
        assert.equal(state.input.selected, true);
        assert.equal(state.button.disabled, false);
        assert.match(state.message.textContent, /Bitte kopiere/);
    });
}
