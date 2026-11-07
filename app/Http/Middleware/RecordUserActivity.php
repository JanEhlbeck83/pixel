<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('api.community.phase-updates')) {
            return $next($request);
        }

        $user = $request->user();
        if ($user && ($user->last_active_at === null || $user->last_active_at->lte(now()->subMinute()))) {
            $user->forceFill(['last_active_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
