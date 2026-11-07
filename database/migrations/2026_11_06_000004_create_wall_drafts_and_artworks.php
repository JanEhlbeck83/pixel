<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wall_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_hash', 64)->nullable()->unique();
            $table->string('title', 80)->default('');
            $table->json('pixels');
            $table->json('paid_cells');
            $table->unsignedInteger('version')->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('wall_artworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 80);
            $table->json('pixels');
            $table->boolean('is_public')->default(false)->index();
            $table->unsignedInteger('wall_x');
            $table->unsignedInteger('wall_y');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wall_artworks');
        Schema::dropIfExists('wall_drafts');
    }
};
