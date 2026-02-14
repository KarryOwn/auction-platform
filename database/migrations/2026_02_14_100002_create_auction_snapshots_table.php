<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Periodic snapshots of auction state for analytics,
     * charting, and auditing price progression.
     */
    public function up(): void
    {
        Schema::create('auction_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->onDelete('cascade');
            $table->decimal('price', 15, 2);
            $table->unsignedInteger('bid_count');
            $table->unsignedInteger('unique_bidders');
            $table->unsignedInteger('watcher_count')->default(0);
            $table->json('metadata')->nullable(); // extensible: avg bid gap, velocity, etc.
            $table->timestamp('captured_at', precision: 6)->useCurrent();

            $table->index(['auction_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_snapshots');
    }
};
