<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pixels', function (Blueprint $table) {
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('pixels', function (Blueprint $table) {
            $table->dropIndex(['updated_at']);
        });
    }
};
