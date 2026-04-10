<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy compatibility migration kept to satisfy older migration history.
     */
    public function up(): void
    {
        // Intentionally left blank.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            Schema::dropIfExists('gateway_transactions');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};