<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('notify_new_listings')->default(true);
            $table->timestamps();

            $table->unique(['follower_id', 'seller_id']);
            $table->index(['seller_id', 'notify_new_listings']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_followers');
    }
};
