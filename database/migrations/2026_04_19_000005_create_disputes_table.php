<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claimant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('respondent_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['item_not_received', 'not_as_described', 'non_payment', 'other']);
            $table->text('description');
            $table->enum('status', ['open', 'under_review', 'resolved_buyer', 'resolved_seller', 'closed'])->default('open');
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->json('evidence_urls')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index(['auction_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
