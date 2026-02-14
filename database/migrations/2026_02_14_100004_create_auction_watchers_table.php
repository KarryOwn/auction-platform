<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Users watching an auction receive notifications.
     * Also used for watcher count display and analytics.
     */
    public function up(): void
    {
        Schema::create('auction_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('notify_outbid')->default(true);
            $table->boolean('notify_ending')->default(true);
            $table->boolean('notify_cancelled')->default(false);
            $table->timestamps();

            $table->unique(['auction_id', 'user_id']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_watchers');
    }
};
