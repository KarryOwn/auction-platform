<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enhance bids table with high-frequency columns:
     * bid type tracking, auto-bid flag, previous amount snapshot,
     * and optimized indexes for throughput queries.
     */
    public function up(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            // Bid type: manual, auto, proxy
            $table->string('bid_type', 10)->default('manual')->after('amount');

            // Snapshot of the price before this bid (for delta analysis)
            $table->decimal('previous_amount', 15, 2)->nullable()->after('bid_type');

            // Auto-bid link (if triggered by an auto-bid rule)
            $table->foreignId('auto_bid_id')->nullable()->after('user_agent')
                  ->constrained('auto_bids')->nullOnDelete();

            // Was this bid placed during a snipe-extension window?
            $table->boolean('is_snipe_bid')->default(false)->after('auto_bid_id');

            // Optimized composite indexes
            $table->index(['auction_id', 'created_at']);          // timeline queries
            $table->index(['user_id', 'created_at']);             // user bid history
            $table->index(['auction_id', 'user_id', 'amount']);   // unique bidder lookups
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->dropForeign(['auto_bid_id']);
            $table->dropIndex(['auction_id', 'created_at']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['auction_id', 'user_id', 'amount']);
            $table->dropColumn([
                'bid_type', 'previous_amount', 'auto_bid_id', 'is_snipe_bid',
            ]);
        });
    }
};
