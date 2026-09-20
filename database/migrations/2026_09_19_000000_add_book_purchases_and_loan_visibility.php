<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->timestamp('hidden_by_user_at')->nullable()->after('returned_at')->index();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('book_id')->nullable()->after('loan_id')->constrained()->nullOnDelete();
        });

        Schema::create('book_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamp('granted_at');
            $table->timestamps();
            $table->unique(['user_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_purchases');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('book_id');
        });

        Schema::table('loans', function (Blueprint $table): void {
            $table->dropColumn('hidden_by_user_at');
        });
    }
};
