export function initGameColorSettings() {
    const mobile = window.matchMedia('(max-width: 760px), (max-width: 1024px) and (max-height: 500px)');
    document.querySelectorAll('[data-game-color-settings]').forEach((settings) => {
        const summary = settings.querySelector('summary');
        const update = () => { settings.open = !mobile.matches; };
        update();
        mobile.addEventListener('change', update);
        document.addEventListener('click', (event) => {
            if (mobile.matches && !settings.contains(event.target)) settings.open = false;
        });
        document.addEventListener('pointerdown', (event) => {
            if (mobile.matches && settings.open && !settings.contains(event.target) && event.target.closest('canvas')) {
                settings.open = false;
                summary.focus();
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, { capture: true });
        settings.addEventListener('keydown', (event) => {
            if (mobile.matches && event.key === 'Escape' && settings.open) {
                event.preventDefault();
                event.stopPropagation();
                settings.open = false;
                summary.focus();
            }
        });
        settings.addEventListener('focusout', (event) => {
            if (mobile.matches && !settings.contains(event.relatedTarget)) settings.open = false;
        });
    });
}

export function initTouchPan(canvas, { enabled, move, finish, tap }) {
    let gesture = null;
    const consume = (event) => {
        event.preventDefault();
        event.stopImmediatePropagation();
    };
    const end = (event, cancelled = false) => {
        if (!gesture || event.pointerId !== gesture.id) return;
        consume(event);
        const completed = gesture;
        gesture = null;
        if (canvas.hasPointerCapture?.(completed.id)) canvas.releasePointerCapture(completed.id);
        if (completed.moved) finish();
        else if (!cancelled) tap(event);
    };
    canvas.addEventListener('pointerdown', (event) => {
        if (event.pointerType !== 'touch' || !enabled()) return;
        consume(event);
        if (gesture || event.isPrimary === false) return;
        gesture = { id: event.pointerId, x: event.clientX, y: event.clientY, moved: false };
        canvas.setPointerCapture(event.pointerId);
    }, { capture: true });
    canvas.addEventListener('pointermove', (event) => {
        if (!gesture || gesture.id !== event.pointerId) return;
        consume(event);
        const dx = event.clientX - gesture.x;
        const dy = event.clientY - gesture.y;
        if (!gesture.moved && Math.hypot(dx, dy) < 6) return;
        gesture.moved = true;
        gesture.x = event.clientX;
        gesture.y = event.clientY;
        move(dx, dy);
    }, { capture: true });
    canvas.addEventListener('pointerup', (event) => end(event), { capture: true });
    canvas.addEventListener('pointercancel', (event) => end(event, true), { capture: true });
    canvas.addEventListener('lostpointercapture', (event) => {
        if (!gesture || gesture.id !== event.pointerId) return;
        const moved = gesture.moved;
        gesture = null;
        if (moved) finish();
    });
}
