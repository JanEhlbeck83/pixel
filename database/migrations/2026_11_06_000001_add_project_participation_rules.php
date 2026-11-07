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
            $table->timestamp('participation_rules_started_at')->nullable();
            $table->timestamp('recruitment_close_at')->nullable();
            $table->timestamp('staffing_close_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });
        DB::table('community_projects')
            ->whereIn('status', ['proposals', 'voting', 'awaiting_reservation', 'active'])
            ->update(['participation_rules_started_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('community_projects', function (Blueprint $table) {
            $table->dropColumn(['participation_rules_started_at', 'recruitment_close_at', 'staffing_close_at', 'cancelled_at']);
        });
    }
};
