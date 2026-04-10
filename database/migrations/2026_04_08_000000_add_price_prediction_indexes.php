<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL-specific indexes with CONCURRENTLY
            DB::statement("
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_auctions_completed_closed
                ON auctions (status, closed_at)
                WHERE status = 'completed' AND deleted_at IS NULL
            ");

            DB::statement("
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_aav_attr_value
                ON auction_attribute_values (attribute_id, lower(value))
            ");

            DB::statement("
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ac_category_id
                ON auction_category (category_id)
            ");
        } else {
            // Fallback for SQLite/MySQL using Laravel Schema builder
            Schema::table('auctions', function (Blueprint $table) {
                $table->index(['status', 'closed_at'], 'idx_auctions_status_closed');
            });

            Schema::table('auction_attribute_values', function (Blueprint $table) {
                $table->index(['attribute_id'], 'idx_aav_attribute_id');
            });

            Schema::table('auction_category', function (Blueprint $table) {
                $table->index(['category_id'], 'idx_ac_category_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_auctions_completed_closed');
            DB::statement('DROP INDEX IF EXISTS idx_aav_attr_value');
            DB::statement('DROP INDEX IF EXISTS idx_ac_category_id');
        } else {
            Schema::table('auctions', function (Blueprint $table) {
                $table->dropIndex('idx_auctions_status_closed');
            });

            Schema::table('auction_attribute_values', function (Blueprint $table) {
                $table->dropIndex('idx_aav_attribute_id');
            });

            Schema::table('auction_category', function (Blueprint $table) {
                $table->dropIndex('idx_ac_category_id');
            });
        }
    }
};
