<?php

namespace App\Console\Commands;

use App\Models\AnalyticsVisit;
use Illuminate\Console\Command;

class PruneAnalyticsVisits extends Command
{
    protected $signature = 'analytics:prune';

    protected $description = 'Delete anonymous visit statistics older than 90 days.';

    public function handle(): int
    {
        $deleted = AnalyticsVisit::query()
            ->where('visit_date', '<', now()->subDays(90)->toDateString())
            ->delete();

        $this->info("Deleted {$deleted} expired analytics rows.");

        return self::SUCCESS;
    }
}
