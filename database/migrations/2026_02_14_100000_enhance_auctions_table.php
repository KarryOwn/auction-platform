<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enhance the auctions table with high-frequency bidding columns,
     * reserve-price support, bid-increment rules, and anti-snipe extension.
     */
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            // Reserve price — hidden minimum the seller will accept
            $table->decimal('reserve_price', 15, 2)->nullable()->after('current_price');
            $table->boolean('reserve_met')->default(false)->after('reserve_price');

            // Bid increment rules
            $table->decimal('min_bid_increment', 15, 2)->default(1.00)->after('reserve_met');

            // Anti-snipe extension (seconds added when bid arrives near end)
            $table->unsignedInteger('snipe_threshold_seconds')->default(30)->after('min_bid_increment');
            $table->unsignedInteger('snipe_extension_seconds')->default(30)->after('snipe_threshold_seconds');
            $table->unsignedInteger('extension_count')->default(0)->after('snipe_extension_seconds');
            $table->unsignedInteger('max_extensions')->default(10)->after('extension_count');

            // Currency support
            $table->string('currency', 3)->default('USD')->after('max_extensions');

            // Featured / promoted
            $table->boolean('is_featured')->default(false)->after('currency');
            $table->timestamp('featured_until')->nullable()->after('is_featured');

            // Winner tracking (set when auction completes)
            $table->foreignId('winner_id')->nullable()->after('featured_until')
                  ->constrained('users')->nullOnDelete();
            $table->decimal('winning_bid_amount', 15, 2)->nullable()->after('winner_id');

            // Bid counter cache for fast reads
            $table->unsignedInteger('bid_count')->default(0)->after('winning_bid_amount');
            $table->unsignedInteger('unique_bidder_count')->default(0)->after('bid_count');

            // Soft-close timestamp (actual close, may differ from end_time due to extensions)
            $table->timestamp('closed_at')->nullable()->after('unique_bidder_count');

            // Composite indexes for high-frequency queries
            $table->index(['status', 'end_time']);          // active auctions ending soonest
            $table->index(['status', 'is_featured']);       // featured active auctions
            $table->index(['user_id', 'status']);           // seller's auctions by status
            $table->index(['winner_id']);                   // user's won auctions
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropForeign(['winner_id']);
            $table->dropIndex(['status', 'end_time']);
            $table->dropIndex(['status', 'is_featured']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['winner_id']);
            $table->dropColumn([
                'reserve_price', 'reserve_met', 'min_bid_increment',
                'snipe_threshold_seconds', 'snipe_extension_seconds',
                'extension_count', 'max_extensions',
                'currency', 'is_featured', 'featured_until',
                'winner_id', 'winning_bid_amount',
                'bid_count', 'unique_bidder_count', 'closed_at',
            ]);
        });
    }
};
