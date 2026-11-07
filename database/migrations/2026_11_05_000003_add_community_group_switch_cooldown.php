<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('last_left_community_group_id')
                ->nullable()
                ->after('id')
                ->constrained('community_groups')
                ->nullOnDelete();
            $table->timestamp('community_group_left_at')->nullable()->after('last_left_community_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_left_community_group_id');
            $table->dropColumn('community_group_left_at');
        });
    }
};
