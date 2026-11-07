<?php

namespace App\Console\Commands;

use App\Models\CommunityProject;
use App\Services\Community\ProjectLifecycle;
use Illuminate\Console\Command;

class AdvanceCommunityProjectsCommand extends Command
{
    protected $signature = 'community:advance-projects';

    protected $description = 'Start scheduled projects and expire signup or completion deadlines';

    public function handle(ProjectLifecycle $lifecycle): int
    {
        CommunityProject::whereIn('status', CommunityProject::RUNNING_STATUSES)
            ->orderBy('id')->chunkById(100, function ($projects) use ($lifecycle) {
                foreach ($projects as $project) {
                    $lifecycle->refresh($project);
                }
            });

        return self::SUCCESS;
    }
}
