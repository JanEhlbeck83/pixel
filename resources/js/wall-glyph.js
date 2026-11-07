import { t, apiFetch } from './i18n';

export function glyphPreview(points, origin, color, readColor, blocked, width, height) {
    let cost = 0;
    let valid = true;
    const pixels = points.map(([dx, dy]) => {
        const x = origin.x + dx;
        const y = origin.y + dy;
        const previous = readColor(x, y);
        if (x < 0 || y < 0 || x >= width || y >= height || previous === null || blocked(x, y)) valid = false;
        if (previous?.toLowerCase() !== color.toLowerCase()) cost++;
        return { x, y };
    });
    return { pixels, cost, valid };
}

export function initWallGlyph(options) {
    const toggle = document.getElementById('wallGlyphToggle');
    const panel = document.getElementById('wallGlyphPanel');
    const catalogElement = document.getElementById('wallGlyphCatalog');
    if (!toggle || !panel || !catalogElement) return null;
    const catalog = JSON.parse(catalogElement.textContent);
    const font = panel.querySelector('[data-glyph-font]');
    const size = panel.querySelector('[data-glyph-size]');
    const letters = panel.querySelector('[data-glyph-letters]');
    const status = panel.querySelector('[data-glyph-status]');
    let active = false;
    let expanded = false;
    let saving = false;
    let selected = 'A';
    let origin = null;
    let quota = null;
    let buttonState = null;
    const buttons = [];
    const points = (letter = selected) => catalog.fonts[font.value][size.value][letter];
    const available = () => quota ? quota.free + (quota.credits || 0) : 0;
    const preview = () => origin && glyphPreview(points(), origin, options.color(), options.readColor, options.blocked, ...options.dimensions());
    const update = () => {
        const nextButtonState = `${font.value}:${size.value}:${selected}:${available()}:${Boolean(quota)}:${saving}`;
        if (buttonState !== nextButtonState) buttons.forEach((button) => {
            const price = points(button.dataset.letter).length;
            button.disabled = saving || !quota || price > available();
            button.setAttribute('aria-pressed', String(button.dataset.letter === selected));
            button.textContent = `${button.dataset.letter} · ${price}`;
        });
        buttonState = nextButtonState;
        const current = preview();
        const price = current ? current.cost : points().length;
        const valid = current?.valid !== false && price <= available() && quota;
        status.textContent = current?.valid === false
            ? t('Vorschau enthält gesperrte, reservierte oder noch nicht geladene Pixel.')
            : t('Kosten: :cost Pixel · Vorrat: :available', { cost: price, available: available() });
        if (!valid && price > available()) status.textContent += ` · ${t('Nicht genügend Pixel.')}`;
        toggle.setAttribute('aria-pressed', String(active));
        toggle.setAttribute('aria-expanded', String(active && expanded));
        font.disabled = saving;
        size.disabled = saving;
        panel.classList.toggle('hidden', !active);
        panel.classList.toggle('is-compact', !expanded);
        panel.querySelector('[data-glyph-summary]').textContent = expanded ? t('Zeichenstempel') : `${selected} · ${size.value} px`;
        options.render();
    };
    const cancel = () => {
        active = false;
        origin = null;
        update();
    };
    for (const letter of 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789') {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'secondary-button';
        button.dataset.letter = letter;
        button.addEventListener('click', () => { selected = letter; expanded = false; update(); });
        letters.append(button);
        buttons.push(button);
    }
    toggle.addEventListener('click', () => {
        if (saving) return;
        if (!active && !options.enable()) return;
        active = !active;
        expanded = active;
        origin = null;
        update();
    });
    panel.querySelector('[data-glyph-close]').addEventListener('click', cancel);
    panel.querySelector('[data-glyph-edit]').addEventListener('click', () => { expanded = true; update(); });
    font.addEventListener('change', update);
    size.addEventListener('change', update);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && active) cancel();
    });
    const tool = {
        active: () => active,
        busy: () => saving,
        cancel,
        refresh: update,
        quota(value) { quota = value; update(); },
        move(point) {
            if (active && !saving && (origin?.x !== point?.x || origin?.y !== point?.y)) {
                origin = point;
                update();
            }
        },
        draw(ctx, offsetX, offsetY) {
            if (!active || !origin) return;
            const current = preview();
            ctx.save();
            ctx.globalAlpha = 0.65;
            ctx.fillStyle = current.valid && current.cost <= available() ? options.color() : '#ff2d95';
            current.pixels.forEach((p) => ctx.fillRect(p.x - offsetX, p.y - offsetY, 1, 1));
            ctx.restore();
        },
        async place(point) {
            if (!active || saving) return;
            origin = point;
            const current = preview();
            if (!current.valid || !quota || current.cost > available()) {
                update();
                options.status(status.textContent);
                return;
            }
            if (current.cost === 0) {
                options.status(t('Dieses Zeichen hat bereits die gewählte Farbe.'));
                return;
            }
            const color = options.color();
            saving = true;
            update();
            try {
                const response = await apiFetch('/api/pixels/glyph', {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ font: font.value, size: Number(size.value), letter: selected, x: point.x, y: point.y, color, cost: current.cost }),
                });
                const payload = await response.json();
                if (!response.ok) {
                    if (payload.quota) options.quota(payload.quota);
                    if (payload.current_pixels) options.sync(payload.current_pixels);
                    throw new Error(payload.message || t('Das Zeichen konnte nicht gesetzt werden.'));
                }
                options.saved(payload.pixels, color, payload.quota);
                active = false;
                origin = null;
                options.status(t('Zeichen gesetzt: :cost Pixel verbraucht.', { cost: payload.count }));
            } catch (error) {
                options.status(error.message);
                status.textContent = error.message;
                console.error('Glyph placement failed:', error);
            } finally {
                saving = false;
                font.disabled = false;
                size.disabled = false;
                if (!active) update();
                else {
                    buttons.forEach((button) => { button.disabled = points(button.dataset.letter).length > available(); });
                }
            }
        },
    };
    return tool;
}
