import { t, apiFetch } from './i18n';

export function draftPoint(event, canvas) {
    const rect = canvas.getBoundingClientRect();
    const x = Math.floor((event.clientX - rect.left) * 20 / rect.width);
    const y = Math.floor((event.clientY - rect.top) * 50 / rect.height);
    return x >= 0 && x < 20 && y >= 0 && y < 50 ? { x, y, index: y * 20 + x } : null;
}

export function draftPreview(pixels, origin, blocked, width, height) {
    const points = Object.entries(pixels).map(([index, color]) => ({
        x: origin.x + Number(index) % 20, y: origin.y + Math.floor(Number(index) / 20), color,
    }));
    return {
        points,
        valid: points.length > 0 && points.every((p) => p.x >= 0 && p.y >= 0 && p.x < width && p.y < height && !blocked(p.x, p.y)),
    };
}

export function initWallDraft(options) {
    const toggle = document.getElementById('wallDraftToggle');
    const panel = document.getElementById('wallDraftPanel');
    const placement = document.getElementById('wallDraftPlacement');
    if (!toggle || !panel || !placement) return null;
    const find = (selector) => panel.querySelector(`[data-draft-${selector}]`);
    const canvas = find('canvas');
    const ctx = canvas.getContext('2d');
    const viewport = find('viewport');
    const title = find('title');
    const zoomSelect = find('zoom');
    const status = find('status');
    const select = find('select');
    const confirm = placement.querySelector('[data-draft-confirm]');
    const placementStatus = placement.querySelector('[data-draft-placement-status]');
    let items = [];
    let draft = null;
    let pending = new Map();
    let open = false;
    let armed = false;
    let origin = null;
    let pinned = false;
    let saving = false;
    let loading = false;
    let erasing = false;
    let stroke = null;
    let lastPoint = null;
    let quota = null;
    let zoom = 6;
    let keyboard = { x: 0, y: 0 };
    const available = () => quota ? quota.free + (quota.credits || 0) : 0;
    const pixels = () => {
        const result = { ...draft?.pixels };
        pending.forEach((color, index) => {
            if (color === null) delete result[index];
            else result[index] = color;
        });
        return result;
    };
    const preview = () => origin && draftPreview(draft.pixels, origin, options.blocked, ...options.dimensions());
    const dirty = () => pending.size > 0 || (draft && title.value !== draft.title);
    const message = (text) => { status.textContent = text; };
    const update = () => {
        const busy = saving || loading || stroke !== null;
        toggle.disabled = saving || loading;
        toggle.setAttribute('aria-expanded', String(open));
        panel.classList.toggle('is-open', open);
        panel.inert = !open;
        placement.classList.toggle('hidden', !armed);
        title.disabled = busy || !draft;
        select.disabled = busy || dirty();
        find('save').disabled = busy;
        find('place').disabled = busy || !draft || dirty() || Object.keys(draft.pixels).length === 0;
        find('erase').disabled = busy || !draft;
        find('erase').setAttribute('aria-pressed', String(erasing));
        confirm.disabled = saving || !pinned || preview()?.valid !== true;
        canvas.setAttribute('aria-busy', String(busy));
        if (armed && !saving) placementStatus.textContent = origin
            ? preview().valid
                ? t('X :x, Y :y · Bereits bezahlt. Zum Veröffentlichen bestätigen.', { x: origin.x, y: origin.y })
                : t('Hier sind Pixel gesperrt oder reserviert. Bitte wähle eine andere Stelle.')
            : t('Klicke auf eine freie Stelle. Vor der Bestätigung wird nichts veröffentlicht.');
        options.render();
    };
    const draw = () => {
        ctx.clearRect(0, 0, 20, 50);
        Object.entries(pixels()).forEach(([index, color]) => {
            ctx.fillStyle = color;
            ctx.fillRect(Number(index) % 20, Math.floor(Number(index) / 20), 1, 1);
        });
    };
    const install = (value) => {
        draft = value;
        pending.clear();
        title.value = draft.title;
        const index = items.findIndex((item) => item.id === value.id);
        if (index >= 0) items[index] = value;
        select.replaceChildren(...items.map((item, index) => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.title || t('Entwurf :number', { number: index + 1 });
            return option;
        }));
        select.value = String(value.id);
        draw();
        update();
    };
    const request = async (url, method, body) => {
        const response = await apiFetch(url, {
            method,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            ...(body ? { body: JSON.stringify(body) } : {}),
        });
        const data = await response.json();
        if (data.quota) options.quota(data.quota);
        if (!response.ok) {
            if (data.draft) install(data.draft);
            throw new Error(data.message || t('Der Entwurf konnte nicht gespeichert werden.'));
        }
        return data;
    };
    const load = async () => {
        loading = true;
        update();
        try {
            const data = await request('/api/wall-drafts', 'GET');
            items = data.drafts;
            if (!items.length) throw new Error(t('Der Entwurf konnte nicht geladen werden.'));
            find('selector').classList.toggle('hidden', items.length < 2);
            install(items[0]);
            message(t('Bereit · :paid Felder bezahlt', { paid: draft.paid_cells.length }));
        } catch (error) {
            message(error.message);
            console.error('Draft loading failed:', error);
        } finally {
            loading = false;
            update();
        }
    };
    const save = async () => {
        if (saving || loading || !draft || stroke) return false;
        if (!dirty()) return true;
        saving = true;
        message(t('Entwurf wird gespeichert …'));
        update();
        try {
            const data = await request(`/api/wall-drafts/${draft.id}`, 'PATCH', {
                version: draft.version, title: title.value,
                changes: [...pending].map(([index, color]) => ({ index, color })),
            });
            install(data.draft);
            message(t('−:cost Pixel · :paid Felder bezahlt', { cost: data.cost, paid: draft.paid_cells.length }));
            return true;
        } catch (error) {
            message(error.message);
            options.status(error.message);
            console.error('Draft saving failed:', error);
            return false;
        } finally {
            saving = false;
            update();
        }
    };
    const setZoom = (value) => {
        const cx = (viewport.scrollLeft + viewport.clientWidth / 2) / zoom;
        const cy = (viewport.scrollTop + viewport.clientHeight / 2) / zoom;
        zoom = value;
        if (![...zoomSelect.options].some((option) => Number(option.value) === value)) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = `${value * 100}%`;
            zoomSelect.append(option);
        }
        zoomSelect.value = String(value);
        canvas.style.width = `${20 * zoom}px`;
        canvas.style.height = `${50 * zoom}px`;
        canvas.style.backgroundSize = `${zoom * 2}px ${zoom * 2}px`;
        viewport.scrollLeft = cx * zoom - viewport.clientWidth / 2;
        viewport.scrollTop = cy * zoom - viewport.clientHeight / 2;
    };
    const fit = () => {
        setZoom(Math.max(2, Math.min(20, Math.floor(Math.min((viewport.clientWidth - 24) / 20, (viewport.clientHeight - 24) / 50)))));
        viewport.scrollTop = 0;
        viewport.scrollLeft = 0;
    };
    const openEditor = async () => {
        if (!options.editable()) return;
        options.exclusive();
        armed = false;
        open = true;
        origin = null;
        pinned = false;
        update();
        if (!draft && !loading) await load();
        fit();
    };
    toggle.addEventListener('click', () => {
        if (saving || loading) return;
        if (open) { open = false; update(); save(); }
        else openEditor();
    });
    find('close').addEventListener('click', () => { open = false; update(); save(); toggle.focus(); });
    find('save').addEventListener('click', () => draft ? save() : load());
    title.addEventListener('change', save);
    title.addEventListener('input', update);
    select.addEventListener('change', () => {
        const selected = items.find((item) => item.id === Number(select.value));
        if (selected) install(selected);
    });
    find('erase').addEventListener('click', () => { erasing = !erasing; update(); });
    zoomSelect.addEventListener('change', () => setZoom(Number(zoomSelect.value)));
    find('zoom-out').addEventListener('click', () => setZoom(Math.max(2, zoom - 2)));
    find('zoom-in').addEventListener('click', () => setZoom(Math.min(20, zoom + 2)));
    find('fit').addEventListener('click', fit);
    const paint = (point) => {
        if (!point || !draft || saving || loading || armed) return;
        const color = erasing ? null : options.color();
        if ((pixels()[point.index] || null) === color) return;
        if (color !== null && !draft.paid_cells.includes(point.index)) {
            const newCells = [...pending].filter(([index, value]) => index !== point.index && value !== null && !draft.paid_cells.includes(index)).length;
            if (newCells >= available()) {
                message(t('Nicht genügend Pixel. Bereits bezahlte Felder kannst du weiter bearbeiten.'));
                return;
            }
        }
        pending.set(point.index, color);
        keyboard = { x: point.x, y: point.y };
        draw();
        update();
    };
    const paintLine = (point) => {
        if (!point) { lastPoint = null; return; }
        const from = lastPoint || point;
        const steps = Math.max(Math.abs(point.x - from.x), Math.abs(point.y - from.y));
        for (let step = 0; step <= steps; step++) {
            const x = Math.round(from.x + (point.x - from.x) * (steps ? step / steps : 0));
            const y = Math.round(from.y + (point.y - from.y) * (steps ? step / steps : 0));
            paint({ x, y, index: y * 20 + x });
        }
        lastPoint = point;
    };
    canvas.addEventListener('pointerdown', (event) => {
        if (event.button !== 0 || event.isPrimary === false || saving || loading || !draft) return;
        event.preventDefault();
        stroke = event.pointerId;
        canvas.setPointerCapture(event.pointerId);
        lastPoint = null;
        paintLine(draftPoint(event, canvas));
    });
    canvas.addEventListener('pointermove', (event) => {
        if (stroke === event.pointerId) paintLine(draftPoint(event, canvas));
    });
    const endStroke = () => { if (stroke !== null) { stroke = null; lastPoint = null; update(); save(); } };
    canvas.addEventListener('pointerup', endStroke);
    canvas.addEventListener('pointercancel', endStroke);
    canvas.addEventListener('lostpointercapture', endStroke);
    window.addEventListener('blur', endStroke);
    canvas.addEventListener('keydown', (event) => {
        const direction = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[event.key];
        if (direction) {
            event.preventDefault();
            keyboard.x = Math.max(0, Math.min(19, keyboard.x + direction[0]));
            keyboard.y = Math.max(0, Math.min(49, keyboard.y + direction[1]));
            message(t('X :x, Y :y', keyboard));
        } else if (event.key === ' ' || event.key === 'Enter') {
            event.preventDefault();
            paint({ ...keyboard, index: keyboard.y * 20 + keyboard.x });
            save();
        }
    });
    find('place').addEventListener('click', async () => {
        if (!await save() || !options.enable()) return;
        open = false;
        armed = true;
        pinned = false;
        origin = null;
        update();
    });
    placement.querySelector('[data-draft-back]').addEventListener('click', () => { if (!saving) openEditor(); });
    confirm.addEventListener('click', async () => {
        if (saving || !pinned || !preview()?.valid) return;
        saving = true;
        update();
        placementStatus.textContent = t('Bild wird veröffentlicht …');
        try {
            const data = await request(`/api/wall-drafts/${draft.id}/publish`, 'POST', { version: draft.version, x: origin.x, y: origin.y });
            options.saved(data.pixels, data.quota);
            armed = false;
            origin = null;
            pinned = false;
            install(data.draft);
            message(t('Bereit · :paid Felder bezahlt', { paid: 0 }));
            options.status(t('Bild veröffentlicht. Keine zusätzlichen Pixel verbraucht.'));
        } catch (error) {
            options.status(error.message);
            placementStatus.textContent = error.message;
            console.error('Draft publication failed:', error);
        } finally {
            saving = false;
            confirm.disabled = !pinned || preview()?.valid !== true;
            options.render();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !saving) {
            open = false;
            armed = false;
            update();
            save();
        }
    });
    window.addEventListener('beforeunload', (event) => {
        if (saving || dirty()) { event.preventDefault(); event.returnValue = ''; }
    });
    setZoom(6);
    const tool = {
        active: () => armed,
        editing: () => open,
        busy: () => saving || loading,
        cancel() {
            if (saving) return;
            open = false;
            armed = false;
            origin = null;
            pinned = false;
            update();
            save();
        },
        refresh: update,
        quota(value) { quota = value; },
        move(point) { if (armed && !pinned && !saving) { origin = point; update(); } },
        place(point) {
            if (!armed || saving) return;
            origin = point;
            pinned = true;
            update();
        },
        draw(context, offsetX, offsetY) {
            if (!armed || !origin) return;
            const current = preview();
            context.save();
            context.globalAlpha = 0.65;
            current.points.forEach((p) => {
                context.fillStyle = current.valid ? p.color : '#ff2d95';
                context.fillRect(p.x - offsetX, p.y - offsetY, 1, 1);
            });
            context.restore();
        },
    };
    if (window.location.hash === '#draft') {
        openEditor();
        document.getElementById('closeRulesBtn')?.addEventListener('click', () => { if (!draft) openEditor(); });
    }
    return tool;
}
