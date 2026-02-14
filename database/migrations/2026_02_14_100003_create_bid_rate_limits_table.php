<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user, per-auction rate limit tracking for bid throttling.
     * Works alongside Redis rate limiting as a persistent fallback.
     */
    public function up(): void
    {
        Schema::create('bid_rate_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('auction_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('bid_count')->default(0);
            $table->timestamp('window_start', precision: 6);
            $table->timestamp('window_end', precision: 6);
            $table->timestamp('last_bid_at', precision: 6)->nullable();
            $table->boolean('is_throttled')->default(false);

            $table->unique(['user_id', 'auction_id']);
            $table->index(['auction_id', 'is_throttled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_rate_limits');
    }
};
