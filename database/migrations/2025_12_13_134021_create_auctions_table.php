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
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); 
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('starting_price', 15, 2);
            $table->decimal('current_price', 15, 2);
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
            $table->timestamps();
            
            $table->index('end_time'); 
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
