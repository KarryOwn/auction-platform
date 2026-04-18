<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('theme', 10)->default('system');
            $table->string('bid_increment_preference', 20)->default('minimum');
            $table->decimal('custom_increment_amount', 10, 2)->nullable();
            $table->json('notification_email')->default(json_encode([]));
            $table->json('notification_push')->default(json_encode([]));
            $table->json('notification_database')->default(json_encode([]));
            $table->boolean('show_bid_history_names')->default(true);
            $table->string('watchlist_email_digest', 10)->default('daily');
            $table->string('timezone', 50)->default('UTC');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
