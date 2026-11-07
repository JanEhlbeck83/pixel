<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_project_proposals', function (Blueprint $table) {
            $table->unsignedInteger('crop_x')->default(0);
            $table->unsignedInteger('crop_y')->default(0);
        });
        Schema::table('community_projects', function (Blueprint $table) {
            $table->string('template_image_path', 2048)->nullable();
            $table->unsignedInteger('crop_x')->default(0);
            $table->unsignedInteger('crop_y')->default(0);
        });
        DB::table('community_projects')->orderBy('id')->each(function ($project) {
            $winner = DB::table('community_project_proposals')
                ->where('community_project_id', $project->id)
                ->select('community_project_proposals.*')
                ->selectSub(DB::table('community_project_votes')->selectRaw('count(*)')
                    ->whereColumn('community_project_proposal_id', 'community_project_proposals.id'), 'vote_count')
                ->orderByDesc('vote_count')->orderBy('id')->first();
            $changes = [];
            if ($project->width === null || $project->height === null) {
                $changes['width'] = $winner?->width ?? 128;
                $changes['height'] = $winner?->height ?? 128;
            }
            if (in_array($project->status, ['awaiting_reservation', 'active'], true)) {
                $changes['template_image_path'] = $winner?->image_path;
            }
            if ($changes) {
                DB::table('community_projects')->where('id', $project->id)->update($changes);
            }
            if (in_array($project->status, ['proposals', 'voting'], true)) {
                DB::table('community_project_proposals')->where('community_project_id', $project->id)->update([
                    'width' => $changes['width'] ?? $project->width,
                    'height' => $changes['height'] ?? $project->height,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('community_projects', fn (Blueprint $table) => $table->dropColumn(['template_image_path', 'crop_x', 'crop_y']));
        Schema::table('community_project_proposals', fn (Blueprint $table) => $table->dropColumn(['crop_x', 'crop_y']));
    }
};
