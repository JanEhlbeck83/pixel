<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_groups', function (Blueprint $table) {
            $table->foreignId('founder_id')->nullable()->change();
            $table->string('emblem', 40)->nullable()->unique()->after('description');
        });
    }

    public function down(): void
    {
        DB::table('community_groups')->whereNull('founder_id')->delete();

        Schema::table('community_groups', function (Blueprint $table) {
            $table->dropUnique(['emblem']);
            $table->dropColumn('emblem');
            $table->foreignId('founder_id')->nullable(false)->change();
        });
    }
};
