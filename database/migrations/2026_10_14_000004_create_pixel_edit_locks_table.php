<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pixel_edit_locks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('x');
            $table->unsignedInteger('y');
            $table->foreignId('locked_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->unique(['x', 'y']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pixel_edit_locks');
    }
};