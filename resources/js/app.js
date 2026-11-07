import './bootstrap';
import './analytics';

import Alpine from 'alpinejs';
import { t, localizedUrl, apiFetch, locale } from './i18n';

window.pixelI18n = { t, localizedUrl, apiFetch, locale };
window.Alpine = Alpine;
Alpine.start();

if (document.getElementById('pixelCanvas')) {
    import('./wall');
}
