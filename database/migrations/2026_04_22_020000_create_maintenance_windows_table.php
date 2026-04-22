<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_windows', function (Blueprint $table) {
            $table->id();
            $table->timestamp('scheduled_start');
            $table->timestamp('scheduled_end');
            $table->string('message', 500)->default('Scheduled maintenance. Back soon.');
            $table->string('status', 20)->default('scheduled');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['status', 'scheduled_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_windows');
    }
};
