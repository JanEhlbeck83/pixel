<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pixels', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('x');
            $table->unsignedInteger('y');
            $table->string('color', 7)->default('#ffffff');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['x', 'y']);
            $table->index(['x', 'y']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pixels');
    }
};
