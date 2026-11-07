<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_projects', function (Blueprint $table) {
            $table->unsignedInteger('minimum_participants')->default(3);
            $table->unsignedInteger('expected_duration_hours')->default(24);
            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('started_at')->nullable();
        });
        // Preserve all legacy project/proposal/pixel data; obsolete phases become read-only archives.
        DB::table('community_projects')->whereIn('status', ['recruiting', 'proposals', 'voting', 'awaiting_reservation'])
            ->update(['status' => 'archived', 'cancellation_reason' => 'legacy_workflow', 'cancelled_at' => now()]);
        DB::table('community_projects')->where('status', 'active')->orderBy('id')->chunkById(100, function ($projects) {
            foreach ($projects as $project) {
                $start = $project->project_close_at
                    ? Carbon::parse($project->project_close_at)->subDays(14)
                    : Carbon::parse($project->created_at);
                DB::table('community_projects')->where('id', $project->id)->update([
                    'started_at' => $start, 'project_close_at' => $start->copy()->addDays(7),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('community_projects', function (Blueprint $table) {
            $table->dropColumn(['minimum_participants', 'expected_duration_hours', 'scheduled_start_at', 'started_at']);
        });
    }
};
