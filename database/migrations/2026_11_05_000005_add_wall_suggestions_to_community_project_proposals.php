<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_project_proposals', function (Blueprint $table) {
            $table->unsignedInteger('suggested_x')->nullable()->after('height');
            $table->unsignedInteger('suggested_y')->nullable()->after('suggested_x');
        });
    }

    public function down(): void
    {
        Schema::table('community_project_proposals', function (Blueprint $table) {
            $table->dropColumn(['suggested_x', 'suggested_y']);
        });
    }
};
