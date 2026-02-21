<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('none')->after('closed_at');
            $table->timestamp('payment_deadline')->nullable()->after('payment_status');

            $table->index(['winner_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropIndex(['winner_id', 'payment_status']);
            $table->dropColumn(['payment_status', 'payment_deadline']);
        });
    }
};
