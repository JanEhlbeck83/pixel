<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletters', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->string('from_name');
            $table->string('from_email');
            $table->string('reply_to')->nullable();
            $table->string('template', 16)->default('neon');
            $table->string('locale', 2)->default('de');
            $table->string('headline')->nullable();
            $table->text('body');
            $table->string('header_image')->nullable();
            $table->string('body_image')->nullable();
            $table->string('button_label')->nullable();
            $table->string('button_url')->nullable();
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('recipient_count')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('newsletter_subscribed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('newsletter_subscribed_at');
        });
        Schema::dropIfExists('newsletters');
    }
};
