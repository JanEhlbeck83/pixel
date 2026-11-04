<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('purchaser_email')->nullable();
            $table->timestamp('withdrawal_consent_at')->nullable();
            $table->string('withdrawal_consent_version', 40)->nullable();
            $table->string('withdrawal_consent_locale', 2)->nullable();
            $table->timestamp('contract_confirmation_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'purchaser_email',
                'withdrawal_consent_at',
                'withdrawal_consent_version',
                'withdrawal_consent_locale',
                'contract_confirmation_sent_at',
            ]);
        });
    }
};
