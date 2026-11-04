<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_visits', function (Blueprint $table) {
            $table->id();
            $table->date('visit_date');
            $table->char('visitor_hash', 64);
            $table->char('country_code', 2)->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('pixels_placed')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at');

            $table->unique(['visit_date', 'visitor_hash']);
            $table->index(['visit_date', 'country_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_visits');
    }
};
