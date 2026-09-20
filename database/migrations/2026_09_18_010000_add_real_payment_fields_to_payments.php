<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payer_phone', 32)->nullable()->after('environment');
            $table->string('checkout_url', 1000)->nullable()->after('external_reference');
            $table->timestamp('status_checked_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payer_phone', 'checkout_url', 'status_checked_at']);
        });
    }
};
