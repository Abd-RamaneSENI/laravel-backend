<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('biography')->nullable();
            $table->timestamps();
        });

        Schema::create('book_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained()->restrictOnDelete();
            $table->foreignId('book_category_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('isbn', 32)->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('cover_url', 2048)->nullable();
            $table->unsignedSmallInteger('published_year')->nullable();
            $table->string('shelf_location', 64)->nullable();
            $table->unsignedInteger('total_copies')->default(1);
            $table->unsignedInteger('available_copies')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'available_copies']);
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->foreignId('loaned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('borrowed_at');
            $table->timestamp('due_at');
            $table->timestamp('returned_at')->nullable();
            $table->string('status', 16)->default('borrowed')->index();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['book_id', 'status']);
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->timestamp('reserved_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->string('status', 16)->default('waiting')->index();
            $table->timestamps();
            $table->index(['user_id', 'book_id', 'status']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('books');
        Schema::dropIfExists('book_categories');
        Schema::dropIfExists('authors');
    }
};
