<?php

namespace App\Http\Controllers;

use App\Services\Analytics;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AnalyticsHeartbeatController extends Controller
{
    public function __invoke(Request $request, Analytics $analytics): Response
    {
        $validated = $request->validate([
            'seconds' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $analytics->recordActiveTime($request, (int) $validated['seconds']);

        return response()->noContent();
    }
}
