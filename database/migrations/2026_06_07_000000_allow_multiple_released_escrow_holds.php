<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE escrow_holds DROP CONSTRAINT IF EXISTS escrow_user_auction_status_unique');
        DB::statement(
            "CREATE UNIQUE INDEX escrow_active_hold_unique ON escrow_holds (user_id, auction_id) WHERE status = 'active'"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS escrow_active_hold_unique');
        DB::statement(
            'ALTER TABLE escrow_holds ADD CONSTRAINT escrow_user_auction_status_unique UNIQUE (user_id, auction_id, status)'
        );
    }
};
