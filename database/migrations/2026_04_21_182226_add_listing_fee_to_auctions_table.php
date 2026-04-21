<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->decimal('listing_fee_charged', 10, 2)->default(0.00)->after('payment_status');
            $table->boolean('listing_fee_paid')->default(false)->after('listing_fee_charged');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['listing_fee_charged', 'listing_fee_paid']);
        });
    }
};
