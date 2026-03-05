<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30)->unique();
            $table->foreignId('auction_id')->constrained()->onDelete('cascade');
            $table->foreignId('buyer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('platform_fee', 15, 2)->default(0.00);
            $table->decimal('seller_amount', 15, 2);
            $table->decimal('total', 15, 2);
            $table->string('currency', 5)->default('USD');
            $table->string('status', 20)->default('issued'); // issued, paid, refunded
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'status']);
            $table->index(['seller_id', 'status']);
            $table->index('auction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
