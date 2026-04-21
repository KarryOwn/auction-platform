<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('vacation_mode')->default(false)->after('seller_rejected_reason');
            $table->timestamp('vacation_mode_started_at')->nullable()->after('vacation_mode');
            $table->timestamp('vacation_mode_ends_at')->nullable()->after('vacation_mode_started_at');
            $table->text('vacation_mode_message')->nullable()->after('vacation_mode_ends_at');
            $table->index('vacation_mode');
        });

        Schema::table('auctions', function (Blueprint $table) {
            $table->boolean('paused_by_vacation')->default(false)->after('ending_soon_notified');
            $table->timestamp('paused_at')->nullable()->after('paused_by_vacation');
            $table->timestamp('original_end_time')->nullable()->after('paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['paused_by_vacation', 'paused_at', 'original_end_time']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['vacation_mode']);
            $table->dropColumn([
                'vacation_mode',
                'vacation_mode_started_at',
                'vacation_mode_ends_at',
                'vacation_mode_message'
            ]);
        });
    }
};
