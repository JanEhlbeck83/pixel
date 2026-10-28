<?php

namespace App\Http\Middleware;

use App\Services\GeoLocale;
use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Leitet Erstbesucher der englischen Startseite "/" einmalig auf die passende Sprache um.
 * Keine Umleitung bei: bewusst gewaehlter Sprache (Cookie), Suchmaschinen/Link-Vorschau-Bots,
 * allen anderen Seiten (Rechtstexte bleiben immer direkt erreichbar).
 */
class GeoLocaleRedirect
{
    public function __construct(private readonly GeoLocale $geo) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET')
            || $request->path() !== '/'
            || $request->hasCookie(config('locales.cookie'))
            || $this->isBot($request)) {
            return $next($request);
        }

        $target = $this->geo->detect($request);

        if ($target === Locales::default() || ! Locales::isSupported($target)) {
            return $next($request);
        }

        return redirect(Locales::url($target, '/', $request->query()))
            ->header('Vary', 'Accept-Language, Cookie')
            ->header('Cache-Control', 'private, no-store');
    }

    private function isBot(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        return $agent === '' || preg_match(config('locales.bot_pattern'), $agent) === 1;
    }
}
