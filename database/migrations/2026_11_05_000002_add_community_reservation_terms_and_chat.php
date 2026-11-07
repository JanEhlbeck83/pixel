<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_projects', function (Blueprint $table) {
            $table->timestamp('reserved_until')->nullable()->index();
        });

        Schema::create('community_project_reservation_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('reservation_term_ends_at');
            $table->timestamps();
            $table->unique(['community_project_id', 'user_id', 'reservation_term_ends_at'], 'community_reservation_votes_unique');
        });

        Schema::create('community_project_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message');
            $table->timestamps();
            $table->index(['community_project_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_project_messages');
        Schema::dropIfExists('community_project_reservation_votes');
        Schema::table('community_projects', function (Blueprint $table) {
            $table->dropColumn('reserved_until');
        });
    }
};
