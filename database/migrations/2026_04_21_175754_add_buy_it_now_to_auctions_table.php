<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->decimal('buy_it_now_price', 15, 2)->nullable()->after('reserve_price');
            $table->boolean('buy_it_now_enabled')->default(false)->after('buy_it_now_price');
            $table->timestamp('buy_it_now_expires_at')->nullable()->after('buy_it_now_enabled');
            $table->string('win_method', 20)->default('bid')->after('winning_bid_amount');

            $table->index(['status', 'buy_it_now_enabled']);
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropIndex(['status', 'buy_it_now_enabled']);
            $table->dropColumn([
                'buy_it_now_price',
                'buy_it_now_enabled',
                'buy_it_now_expires_at',
                'win_method',
            ]);
        });
    }
};
