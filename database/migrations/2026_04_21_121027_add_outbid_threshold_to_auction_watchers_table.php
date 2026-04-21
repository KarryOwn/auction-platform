<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auction_watchers', function (Blueprint $table) {
            $table->decimal('outbid_threshold_amount', 10, 2)->nullable()->after('notify_outbid');
            $table->decimal('price_alert_at', 10, 2)->nullable()->after('outbid_threshold_amount');
            $table->boolean('price_alert_sent')->default(false)->after('price_alert_at');
        });
    }

    public function down(): void
    {
        Schema::table('auction_watchers', function (Blueprint $table) {
            $table->dropColumn(['outbid_threshold_amount', 'price_alert_at', 'price_alert_sent']);
        });
    }
};
