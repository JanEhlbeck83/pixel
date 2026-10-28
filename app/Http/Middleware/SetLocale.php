<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Support\LocalizedUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setzt die Sprache pro Anfrage.
 *  - Seiten in der Sprachgruppe: Sprache steht in der URL (/de, /es, sonst Englisch).
 *  - Alles andere (API, Zahlungs-Rueckspruenge ...): Header X-App-Locale, dann Session, dann Cookie.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $routed = $route && $route->getAction('locale_page');
        $locale = null;

        if ($routed) {
            $name = (string) $route->getName();
            $plain = Str::after($name, LocalizedUrlGenerator::PREFIXED_NAME);
            if ($name !== $plain) {
                // routeIs('login') soll in jeder Sprache greifen.
                $route->action['as'] = $plain;
            }
            $fromSession = in_array($plain, config('locales.session_routes', []), true);
            $locale = $route->parameter('locale') ?? Locales::default();
            $route->forgetParameter('locale');
        } else {
            $fromSession = true;
        }

        if ($fromSession || ! Locales::isSupported($locale)) {
            $locale = $fromSession ? $this->fallback($request) : Locales::default();
        }

        $urlIsAuthoritative = $routed && ! $fromSession;
        $request->attributes->set('locale_routed', $urlIsAuthoritative);

        app()->setLocale($locale);

        if ($urlIsAuthoritative && $request->hasSession()) {
            $request->session()->put('locale', $locale);
        }

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    private function fallback(Request $request): string
    {
        $candidates = [
            $request->header('X-App-Locale'),
            $request->hasSession() ? $request->session()->get('locale') : null,
            $request->cookie(config('locales.cookie')),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && Locales::isSupported($candidate)) {
                return $candidate;
            }
        }

        return Locales::default();
    }
}
