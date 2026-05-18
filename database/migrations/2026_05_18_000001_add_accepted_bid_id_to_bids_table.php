<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->string('accepted_bid_id', 40)->nullable()->after('id');
            $table->unique('accepted_bid_id');
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->dropUnique(['accepted_bid_id']);
            $table->dropColumn('accepted_bid_id');
        });
    }
};
