<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedInteger('invoice_email_attempts')->default(0)->after('invoice_sent_at');
            $table->timestamp('invoice_email_last_attempt_at')->nullable()->after('invoice_email_attempts');
            $table->text('invoice_email_error')->nullable()->after('invoice_email_last_attempt_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'invoice_email_attempts',
                'invoice_email_last_attempt_at',
                'invoice_email_error',
            ]);
        });
    }
};
