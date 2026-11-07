<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_project_participants', function (Blueprint $table) {
            $table->timestamp('voting_abstained_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('community_project_participants', function (Blueprint $table) {
            $table->dropColumn('voting_abstained_at');
        });
    }
};
