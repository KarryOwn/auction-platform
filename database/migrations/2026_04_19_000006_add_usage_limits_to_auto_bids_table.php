<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auto_bids', function (Blueprint $table) {
            $table->unsignedInteger('max_auto_bids')->default(3);
            $table->unsignedInteger('auto_bids_used')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('auto_bids', function (Blueprint $table) {
            $table->dropColumn(['max_auto_bids', 'auto_bids_used']);
        });
    }
};
