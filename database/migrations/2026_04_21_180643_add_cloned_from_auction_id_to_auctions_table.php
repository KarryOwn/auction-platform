<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->unsignedBigInteger('cloned_from_auction_id')->nullable()->after('serial_number');
            $table->foreign('cloned_from_auction_id')->references('id')->on('auctions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropForeign(['cloned_from_auction_id']);
            $table->dropColumn('cloned_from_auction_id');
        });
    }
};
