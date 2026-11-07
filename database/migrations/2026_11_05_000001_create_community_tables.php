<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_wall_tile_mutexes', function (Blueprint $table) {
            $table->unsignedInteger('tile_x');
            $table->unsignedInteger('tile_y');
            $table->primary(['tile_x', 'tile_y']);
        });

        Schema::create('community_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('founder_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 60)->unique();
            $table->string('description', 1000);
            $table->string('status', 20)->default('pending')->index();
            $table->text('admin_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('community_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->timestamps();
            $table->unique(['community_group_id', 'user_id']);
        });

        Schema::create('community_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 100);
            $table->string('type', 20);
            $table->string('status', 30)->default('proposals')->index();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedInteger('reserved_x')->nullable();
            $table->unsignedInteger('reserved_y')->nullable();
            $table->timestamp('proposals_close_at');
            $table->timestamp('voting_close_at')->nullable();
            $table->timestamp('project_close_at')->nullable();
            $table->string('image_path', 2048)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['reserved_x', 'reserved_y']);
        });

        Schema::create('community_project_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->text('description');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->string('image_path', 2048)->nullable();
            $table->timestamps();
            $table->index(['community_project_id', 'created_at']);
            $table->unique(['community_project_id', 'user_id']);
        });

        Schema::create('community_project_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_project_proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['community_project_id', 'user_id']);
        });

        Schema::create('community_project_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['community_project_id', 'user_id']);
        });

        Schema::create('community_project_pixels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('x');
            $table->unsignedSmallInteger('y');
            $table->string('color', 7);
            $table->timestamps();
            $table->unique(['community_project_id', 'x', 'y']);
            $table->index(['community_project_id', 'updated_at']);
        });

        Schema::create('community_project_phase_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phase', 20);
            $table->timestamps();
            $table->unique(['community_project_id', 'user_id', 'phase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_project_phase_votes');
        Schema::dropIfExists('community_project_pixels');
        Schema::dropIfExists('community_project_participants');
        Schema::dropIfExists('community_project_votes');
        Schema::dropIfExists('community_project_proposals');
        Schema::dropIfExists('community_projects');
        Schema::dropIfExists('community_group_members');
        Schema::dropIfExists('community_groups');
        Schema::dropIfExists('community_wall_tile_mutexes');
    }
};
