<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceEmail('admin@seni-cnf-edu.test', 'senicnf@gmail.com');
        $this->replaceEmail('lecteur@seni-cnf-edu.test', 'seniramane@gmail.com');
        $this->replaceEmail('auteur@seni-cnf-edu.test', 'auteur.senicnf@gmail.com');
    }

    public function down(): void
    {
        $this->replaceEmail('senicnf@gmail.com', 'admin@seni-cnf-edu.test');
        $this->replaceEmail('seniramane@gmail.com', 'lecteur@seni-cnf-edu.test');
        $this->replaceEmail('auteur.senicnf@gmail.com', 'auteur@seni-cnf-edu.test');
    }

    private function replaceEmail(string $from, string $to): void
    {
        if (! DB::table('users')->where('email', $to)->exists()) {
            DB::table('users')->where('email', $from)->update(['email' => $to]);
        }
    }
};
