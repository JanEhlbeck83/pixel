<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wall_dimensions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('width')->default(1024);
            $table->unsignedInteger('height')->default(1024);
            $table->timestamps();
        });

        DB::table('wall_dimensions')->insert([
            'id' => 1,
            'width' => 1024,
            'height' => 1024,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('wall_dimensions');
    }
};
