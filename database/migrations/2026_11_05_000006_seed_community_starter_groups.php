<?php

use Database\Seeders\CommunityStarterGroupsSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new CommunityStarterGroupsSeeder)->run();
    }

    public function down(): void
    {
        // Keep groups and their member-created content when rolling back.
    }
};
