<?php

namespace App\Http\Middleware;

use App\Services\Analytics;
use App\Services\GeoLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordAnalyticsVisit
{
    public function __construct(
        private readonly Analytics $analytics,
        private readonly GeoLocale $geoLocale,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $route = $request->route();
        $routeName = $route?->getName();
        $isPublicPage = $route?->getAction('locale_page')
            && in_array($routeName, ['home', 'gallery'], true);

        if ($response->isSuccessful()
            && $request->isMethod('GET')
            && $isPublicPage
            && ! $request->user()?->isAdmin()
            && ! $this->isBot($request)) {
            $this->analytics->recordVisit($request, $this->geoLocale->country($request->ip()));
        }

        return $response;
    }

    private function isBot(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        return $agent === '' || preg_match(config('locales.bot_pattern'), $agent) === 1;
    }
}
