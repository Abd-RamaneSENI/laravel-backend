<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->timestamp('access_expires_at')->nullable()->after('fee_paid_at')->index();
            $table->unsignedInteger('fee_charged_amount')->default(0)->after('fee_amount');
            $table->unsignedInteger('fee_refund_amount')->default(0)->after('fee_charged_amount');
            $table->string('fee_refund_status', 24)->default('none')->after('fee_refund_amount')->index();
            $table->timestamp('fee_refunded_at')->nullable()->after('fee_refund_status');
        });

        DB::table('loans')
            ->whereNotNull('fee_paid_at')
            ->where('fee_status', 'paid')
            ->orderBy('id')
            ->get(['id', 'fee_paid_at', 'fee_amount'])
            ->each(function ($loan): void {
                DB::table('loans')->where('id', $loan->id)->update([
                    'access_expires_at' => Carbon::parse($loan->fee_paid_at)->addDays(30),
                    'fee_charged_amount' => (int) $loan->fee_amount,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'access_expires_at',
                'fee_charged_amount',
                'fee_refund_amount',
                'fee_refund_status',
                'fee_refunded_at',
            ]);
        });
    }
};
