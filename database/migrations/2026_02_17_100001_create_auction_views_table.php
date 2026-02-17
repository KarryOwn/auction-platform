<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('referrer')->nullable();
            $table->string('session_id');
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->index(['auction_id', 'user_id']);
            $table->index(['auction_id', 'session_id']);
            $table->index(['auction_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_views');
    }
};
