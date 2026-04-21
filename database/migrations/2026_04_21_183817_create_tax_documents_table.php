<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period_label', 20);
            $table->string('period_type', 10);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('file_path')->nullable();
            $table->decimal('gross_sales', 15, 2)->default(0);
            $table->decimal('platform_fees_paid', 15, 2)->default(0);
            $table->decimal('listing_fees_paid', 15, 2)->default(0);
            $table->decimal('net_revenue', 15, 2)->default(0);
            $table->decimal('refunds_issued', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'period_label', 'period_type']);
            $table->index(['user_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_documents');
    }
};
