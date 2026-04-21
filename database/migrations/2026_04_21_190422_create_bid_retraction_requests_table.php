<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_retraction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->string('status', 20)->default('pending'); // pending, approved, declined
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->timestamps();

            $table->unique('bid_id'); // one retraction request per bid
            $table->index(['auction_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('bids', function (Blueprint $table) {
            $table->boolean('is_retracted')->default(false)->after('is_snipe_bid');
            $table->timestamp('retracted_at')->nullable()->after('is_retracted');
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->dropColumn(['is_retracted', 'retracted_at']);
        });

        Schema::dropIfExists('bid_retraction_requests');
    }
};
