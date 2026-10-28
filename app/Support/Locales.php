<?php

namespace App\Support;

class Locales
{
    public static function default(): string
    {
        return config('locales.default');
    }

    public static function supported(): array
    {
        return config('locales.supported');
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::supported(), true);
    }

    /** "" fuer Englisch, sonst "/de" bzw. "/es". */
    public static function prefix(string $locale): string
    {
        return $locale === self::default() ? '' : '/'.$locale;
    }

    public static function stripPrefix(string $path): string
    {
        $others = implode('|', array_map('preg_quote', array_diff(self::supported(), [self::default()])));
        $path = preg_replace('#^/(?:'.$others.')(?=/|$)#', '', '/'.ltrim($path, '/'));

        return $path === '' ? '/' : $path;
    }

    /** Absolute URL einer (sprachneutralen) Pfadangabe in der gewuenschten Sprache. */
    public static function url(string $locale, string $path = '/', array $query = []): string
    {
        $path = self::stripPrefix($path);
        $full = $path === '/' ? (self::prefix($locale) ?: '/') : self::prefix($locale).$path;

        return url($full).($query ? '?'.http_build_query($query) : '');
    }

    /** True, wenn die aktuelle Seite in allen Sprachen existiert (Sprachgruppe der Routen). */
    public static function isLocalizedRequest(): bool
    {
        return (bool) request()->attributes->get('locale_routed', false);
    }

    /** Sprachneutraler Pfad der aktuellen Seite; Tokens aus URLs werden nie weitergereicht. */
    public static function currentPath(): string
    {
        $request = request();
        $name = $request->route()?->getName() ?? '';

        if (! $request->isMethod('GET') || ! self::isLocalizedRequest()
            || str_starts_with($name, 'password.reset') || str_starts_with($name, 'verification.')) {
            return '/';
        }

        return self::stripPrefix($request->getPathInfo());
    }

    /** hreflang-Alternativen der aktuellen Seite (leer, wenn nicht lokalisiert). */
    public static function alternates(): array
    {
        if (! self::isLocalizedRequest() || ! request()->isMethod('GET')) {
            return [];
        }

        $path = self::stripPrefix(request()->getPathInfo());
        $urls = [];
        foreach (self::supported() as $locale) {
            $urls[$locale] = self::url($locale, $path);
        }
        $urls['x-default'] = $urls[self::default()];

        return $urls;
    }
}
