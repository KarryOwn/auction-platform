<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_category_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->unsignedInteger('total_auctions')->default(0);
            $table->unsignedInteger('completed_auctions')->default(0);
            $table->unsignedInteger('cancelled_auctions')->default(0);
            $table->decimal('sell_through_rate', 5, 4)->default(0);
            $table->decimal('avg_final_price', 15, 2)->default(0);
            $table->decimal('avg_starting_price', 15, 2)->default(0);
            $table->decimal('price_appreciation_pct', 8, 4)->default(0);
            $table->unsignedInteger('total_bids')->default(0);
            $table->unsignedInteger('unique_bidders')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_category_snapshots');
    }
};
