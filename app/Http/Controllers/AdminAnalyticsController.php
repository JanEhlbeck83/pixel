<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsVisit;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();
        $weekStart = now()->subDays(6)->toDateString();
        $monthStart = now()->subDays(29)->toDateString();

        $summary = collect([
            'today' => ['label' => 'Heute', 'start' => $today],
            'week' => ['label' => 'Letzte 7 Tage', 'start' => $weekStart],
            'month' => ['label' => 'Letzte 30 Tage', 'start' => $monthStart],
        ])->map(function (array $period) use ($today) {
            $query = AnalyticsVisit::query()->whereBetween('visit_date', [$period['start'], $today]);

            return [
                'label' => $period['label'],
                'visitors' => (clone $query)->count(),
                'pixels' => (clone $query)->sum('pixels_placed'),
                'duration' => (int) round((clone $query)->avg('duration_seconds') ?? 0),
            ];
        });

        $daily = AnalyticsVisit::query()
            ->select('visit_date', DB::raw('COUNT(*) as visitors'), DB::raw('SUM(pixels_placed) as pixels'), DB::raw('AVG(duration_seconds) as duration'))
            ->whereBetween('visit_date', [$monthStart, $today])
            ->groupBy('visit_date')
            ->orderByDesc('visit_date')
            ->get()
            ->keyBy(fn (AnalyticsVisit $visit) => $visit->visit_date->toDateString());

        $days = collect(range(0, 29))->map(function (int $offset) use ($daily) {
            $date = now()->subDays($offset)->toDateString();
            $visit = $daily->get($date);

            return [
                'date' => $date,
                'visitors' => (int) ($visit->visitors ?? 0),
                'pixels' => (int) ($visit->pixels ?? 0),
                'duration' => (int) round($visit->duration ?? 0),
            ];
        });

        $countries = AnalyticsVisit::query()
            ->select('country_code', DB::raw('COUNT(*) as visits'))
            ->whereBetween('visit_date', [$monthStart, $today])
            ->whereNotNull('country_code')
            ->groupBy('country_code')
            ->orderByDesc('visits')
            ->limit(10)
            ->get()
            ->map(fn (AnalyticsVisit $visit) => [
                'name' => $this->countryName($visit->country_code),
                'visits' => (int) $visit->visits,
            ]);

        return view('admin.analytics', compact('summary', 'days', 'countries'));
    }

    private function countryName(string $code): string
    {
        if (class_exists(\Locale::class)) {
            return \Locale::getDisplayRegion('-'.$code, app()->getLocale()) ?: $code;
        }

        return $code;
    }
}
