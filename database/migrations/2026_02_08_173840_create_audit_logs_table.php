<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50);           // e.g. 'auction.cancelled', 'user.banned'
            $table->string('target_type', 50);       // e.g. 'auction', 'user', 'bid'
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('metadata')->nullable();    // old/new values, reason, etc.
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at', precision: 6)->useCurrent();

            $table->index(['target_type', 'target_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
