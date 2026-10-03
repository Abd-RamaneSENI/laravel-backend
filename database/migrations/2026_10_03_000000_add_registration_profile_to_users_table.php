<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name', 80)->nullable()->after('name');
            $table->string('last_name', 80)->nullable()->after('first_name');
            $table->date('birth_date')->nullable()->after('email');
            $table->string('phone', 32)->nullable()->after('birth_date');
            $table->string('country', 100)->nullable()->after('phone');
            $table->string('department', 100)->nullable()->after('country');
            $table->string('commune', 100)->nullable()->after('department');
            $table->string('arrondissement', 100)->nullable()->after('commune');
            $table->string('profession', 120)->nullable()->after('arrondissement');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['first_name', 'last_name', 'birth_date', 'phone', 'country', 'department', 'commune', 'arrondissement', 'profession']);
        });
    }
};
