<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escrow_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('auction_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('active'); // active, released, captured, refunded
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            // Only one active hold per user per auction
            $table->unique(['user_id', 'auction_id', 'status'], 'escrow_user_auction_status_unique');
            $table->index(['auction_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escrow_holds');
    }
};
