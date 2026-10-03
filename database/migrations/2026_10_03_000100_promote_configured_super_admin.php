<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $email = env('SUPER_ADMIN_EMAIL');

        if (is_string($email) && $email !== '') {
            DB::table('users')->where('email', $email)->update([
                'role' => 'super_admin',
                'email_verified_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // The administrator role must never be removed automatically.
    }
};
