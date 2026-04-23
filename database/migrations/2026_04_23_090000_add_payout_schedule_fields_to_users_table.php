<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('pending_payout_balance', 12, 2)->default(0)->after('held_balance');
            $table->string('payout_schedule', 20)->default('manual')->after('stripe_connect_onboarded');
            $table->string('payout_schedule_day', 20)->nullable()->after('payout_schedule');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'pending_payout_balance',
                'payout_schedule',
                'payout_schedule_day',
            ]);
        });
    }
};
