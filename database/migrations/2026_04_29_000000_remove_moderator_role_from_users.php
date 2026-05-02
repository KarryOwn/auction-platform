<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'moderator')
            ->update(['role' => 'admin']);
    }

    public function down(): void
    {
        // The moderator role has been removed from application code.
    }
};
