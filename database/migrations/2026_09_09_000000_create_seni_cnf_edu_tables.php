<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('resource_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('resource_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->text('description');
            $table->unsignedInteger('price');
            $table->string('currency', 3)->default('XOF');
            $table->string('status')->default('draft')->index();
            $table->string('visibility')->default('public');
            $table->string('academic_year', 16)->nullable()->index();
            $table->string('private_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('cover_path')->nullable();
            $table->unsignedSmallInteger('page_count')->nullable();
            $table->string('language', 10)->default('fr');
            $table->text('rights_statement')->nullable();
            $table->string('rights_proof_path')->nullable();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('sales_count')->default(0);
            $table->unsignedBigInteger('download_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'visibility']);
        });
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
        Schema::create('resource_tag', function (Blueprint $table) {
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['resource_id', 'tag_id']);
        });
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['cart_id', 'resource_id']);
        });
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type');
            $table->unsignedInteger('value');
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('reference')->unique();
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('total');
            $table->string('currency', 3)->default('XOF');
            $table->string('status')->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->restrictOnDelete();
            $table->string('title_snapshot');
            $table->unsignedInteger('unit_price');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedInteger('total');
            $table->timestamps();
            $table->unique(['order_id', 'resource_id']);
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('environment', 16);
            $table->string('external_reference')->nullable()->index();
            $table->string('internal_reference')->unique();
            $table->unsignedInteger('amount');
            $table->string('currency', 3);
            $table->string('status')->default('initiated')->index();
            $table->string('request_hash', 64)->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('failure_code')->nullable();
            $table->string('failure_message')->nullable();
            $table->timestamps();
        });
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status');
            $table->string('external_reference')->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->string('response_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'attempt_number']);
        });
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('environment', 16);
            $table->string('event_id');
            $table->boolean('signature_valid')->default(false);
            $table->string('payload_hash', 64);
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('status')->default('received');
            $table->string('error_code')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });
        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'resource_id', 'order_id']);
        });
        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entitlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('downloaded_at');
            $table->timestamps();
        });
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'resource_id']);
        });
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment');
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->unique(['user_id', 'resource_id']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->nullableMorphs('subject');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'reviews', 'favorites', 'downloads', 'entitlements', 'webhook_events', 'payment_attempts', 'payments', 'order_items', 'orders', 'coupons', 'cart_items', 'carts', 'resource_tag', 'tags', 'resources', 'resource_types', 'subjects', 'school_classes', 'cycles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
