<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_projects', function (Blueprint $table) {
            $table->timestamp('reservation_close_at')->nullable()->index();
            $table->string('cancellation_reason', 40)->nullable();
        });

        DB::table('community_projects')
            ->where('status', 'awaiting_reservation')
            ->orderBy('id')
            ->chunkById(100, function ($projects): void {
                foreach ($projects as $project) {
                    DB::table('community_projects')
                        ->where('id', $project->id)
                        ->update([
                            'reservation_close_at' => \Illuminate\Support\Carbon::parse($project->updated_at)->addDays(14),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('community_projects', function (Blueprint $table) {
            $table->dropColumn(['reservation_close_at', 'cancellation_reason']);
        });
    }
};
