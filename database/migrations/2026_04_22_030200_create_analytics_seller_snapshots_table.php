<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_seller_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->unsignedInteger('active_listings')->default(0);
            $table->unsignedInteger('completed_sales')->default(0);
            $table->decimal('gross_revenue', 15, 2)->default(0);
            $table->decimal('avg_sale_price', 15, 2)->default(0);
            $table->decimal('avg_rating', 4, 2)->default(0);
            $table->unsignedInteger('total_bids_received')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'report_date']);
            $table->index('report_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_seller_snapshots');
    }
};
