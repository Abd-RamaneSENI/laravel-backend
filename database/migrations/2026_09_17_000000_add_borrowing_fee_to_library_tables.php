<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->unsignedInteger('price')->default(10000);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedInteger('fee_amount')->default(0);
            $table->string('fee_currency', 3)->default(config('payments.currency', 'XOF'));
            $table->string('fee_status', 16)->default('pending')->index();
            $table->timestamp('fee_paid_at')->nullable();
            $table->string('fee_provider', 32)->nullable();
            $table->string('fee_payment_reference', 80)->nullable()->index();
        });

        $loans = DB::table('loans')
            ->join('books', 'loans.book_id', '=', 'books.id')
            ->select('loans.id', 'books.price')
            ->orderBy('loans.id')
            ->get();

        foreach ($loans as $loan) {
            $feeAmount = (int) ceil(((int) $loan->price) * 0.05);
            DB::table('loans')->where('id', $loan->id)->update([
                'fee_amount' => $feeAmount,
                'fee_currency' => config('payments.currency', 'XOF'),
                'fee_status' => $feeAmount > 0 ? 'pending' : 'paid',
                'fee_paid_at' => $feeAmount > 0 ? null : now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'fee_amount',
                'fee_currency',
                'fee_status',
                'fee_paid_at',
                'fee_provider',
                'fee_payment_reference',
            ]);
        });

        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
