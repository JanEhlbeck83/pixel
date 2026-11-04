<?php

namespace App\Services;

use App\Models\AnalyticsVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Analytics
{
    public function recordVisit(Request $request, ?string $countryCode): void
    {
        $now = now();
        $date = $now->toDateString();
        $key = $this->visitorHash($request, $date);

        if ($key === null) {
            return;
        }

        DB::table('analytics_visits')->upsert([[
            'visit_date' => $date,
            'visitor_hash' => $key,
            'country_code' => $countryCode,
            'duration_seconds' => 0,
            'pixels_placed' => 0,
            'started_at' => $now,
            'last_seen_at' => $now,
        ]], ['visit_date', 'visitor_hash'], ['last_seen_at']);
    }

    public function recordActiveTime(Request $request, int $seconds): void
    {
        $key = $this->visitorHash($request, now()->toDateString());

        if ($key === null) {
            return;
        }

        AnalyticsVisit::query()
            ->where('visit_date', now()->toDateString())
            ->where('visitor_hash', $key)
            ->increment('duration_seconds', min($seconds, 30), ['last_seen_at' => now()]);
    }

    public function recordPixels(Request $request, int $count): void
    {
        if ($request->user()?->isAdmin()) {
            return;
        }

        $date = now()->toDateString();
        $key = $this->visitorHash($request, $date);

        if ($key === null) {
            return;
        }

        AnalyticsVisit::query()
            ->where('visit_date', $date)
            ->where('visitor_hash', $key)
            ->increment('pixels_placed', $count, ['last_seen_at' => now()]);
    }

    private function visitorHash(Request $request, string $date): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        return hash_hmac(
            'sha256',
            $request->session()->getId().'|'.$date,
            (string) config('app.key')
        );
    }
}
