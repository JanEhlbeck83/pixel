<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('pixel_credits')->default(0);
            $table->timestamp('last_pixel_at')->nullable();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product', 40);
            $table->string('provider', 20);
            $table->string('provider_reference')->nullable()->index();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('EUR');
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pixel_credits', 'last_pixel_at']);
        });
    }
};
