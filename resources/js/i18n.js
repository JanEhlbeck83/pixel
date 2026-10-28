// Uebersetzungen fuer Browser-Code. Schluessel = deutscher Originaltext (wie in PHP: __('...')).
// Fuer Deutsch ist das Woerterbuch leer, der Schluessel wird unveraendert angezeigt.
const i18n = window.I18N || { locale: 'de', prefix: '/de', messages: {} };

export const locale = i18n.locale;

/** t('Hallo, :name!', { name: 'Ada' }) */
export function t(key, params = {}) {
    let text = Object.prototype.hasOwnProperty.call(i18n.messages, key) ? i18n.messages[key] : key;
    for (const [name, value] of Object.entries(params)) {
        text = text.split(':' + name).join(String(value));
    }
    return text;
}

/** Interne Seite in der aktuellen Sprache: localizedUrl('/profile') => '/de/profile' */
export function localizedUrl(path = '/') {
    const clean = '/' + String(path).replace(/^\/+/, '');
    return clean === '/' ? i18n.prefix || '/' : i18n.prefix + clean;
}

/** fetch() mit Sprachkennung fuer die API (Antworttexte kommen in der Sprache der Seite). */
export function apiFetch(url, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('X-App-Locale', i18n.locale);
    return fetch(url, { ...options, headers });
}
