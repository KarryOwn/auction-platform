<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_fee_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->decimal('starting_price_min', 15, 2)->nullable();
            $table->decimal('starting_price_max', 15, 2)->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('fee_amount', 10, 2)->default(0.00);
            $table->decimal('fee_percent', 5, 4)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_fee_tiers');
    }
};
