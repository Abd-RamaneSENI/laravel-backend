<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reading_document_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();

            $table->unique(['user_id', 'reading_document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_presences');
    }
};
