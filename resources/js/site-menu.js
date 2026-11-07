import { initGameColorSettings } from './game-controls';

initGameColorSettings();

const menu = document.querySelector('.wall-menu');
const mobile = window.matchMedia('(max-width: 760px), (max-width: 1024px) and (max-height: 500px)');
const backdrop = menu?.querySelector('[data-menu-backdrop]');
const menus = [
    { toggle: document.getElementById('wallMenuToggle'), panel: document.getElementById('wallMenuPanel') },
    { toggle: document.getElementById('adminMenuToggle'), panel: document.getElementById('adminMenuPanel') },
].filter(({ toggle, panel }) => toggle && panel);
let current = null;

function closeMenu(restoreFocus = false) {
    if (!current) return;
    const previous = current;
    previous.panel.classList.add('hidden');
    previous.panel.removeAttribute('role');
    previous.panel.removeAttribute('aria-modal');
    previous.toggle.setAttribute('aria-expanded', 'false');
    backdrop?.classList.add('hidden');
    document.body.classList.remove('mobile-menu-open');
    current = null;
    if (restoreFocus) previous.toggle.focus();
}

for (const item of menus) {
    item.toggle.addEventListener('click', () => {
        const alreadyOpen = current === item;
        closeMenu();
        if (alreadyOpen) return;
        current = item;
        item.panel.classList.remove('hidden');
        item.toggle.setAttribute('aria-expanded', 'true');
        if (mobile.matches) {
            item.panel.setAttribute('role', 'dialog');
            item.panel.setAttribute('aria-modal', 'true');
            item.panel.setAttribute('aria-label', item.toggle.textContent.trim());
            backdrop?.classList.remove('hidden');
            document.body.classList.add('mobile-menu-open');
            item.panel.querySelector('[data-menu-close]')?.focus();
        }
    });
    item.panel.querySelector('[data-menu-close]')?.addEventListener('click', () => closeMenu(true));
    item.panel.addEventListener('click', (event) => {
        if (event.target.closest('a, [data-menu-dismiss]')) closeMenu();
    });
}
backdrop?.addEventListener('click', () => closeMenu(true));
document.addEventListener('click', (event) => {
    if (!menu?.contains(event.target)) closeMenu();
});
document.addEventListener('keydown', (event) => {
    if (!current) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopImmediatePropagation();
        closeMenu(true);
    } else if (event.key === 'Tab' && mobile.matches) {
        const controls = [...current.panel.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), textarea, select, summary')]
            .filter((element) => element.getClientRects().length > 0);
        const first = controls[0];
        const last = controls.at(-1);
        if (event.shiftKey && (document.activeElement === first || !current.panel.contains(document.activeElement))) {
            event.preventDefault();
            last?.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !current.panel.contains(document.activeElement))) {
            event.preventDefault();
            first?.focus();
        }
    }
});
mobile.addEventListener('change', () => closeMenu(true));
document.addEventListener('site-menu-close', () => closeMenu());
