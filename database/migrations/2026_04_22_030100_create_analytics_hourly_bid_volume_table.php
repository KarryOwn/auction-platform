<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_hourly_bid_volume', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->unsignedTinyInteger('hour_of_day');
            $table->string('day_of_week', 10);
            $table->unsignedInteger('bid_count')->default(0);
            $table->unsignedInteger('unique_bidders')->default(0);
            $table->unsignedInteger('unique_auctions')->default(0);
            $table->timestamps();

            $table->unique(['report_date', 'hour_of_day']);
            $table->index(['day_of_week', 'hour_of_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_hourly_bid_volume');
    }
};
