<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auto_bids', function (Blueprint $table) {
            $table->decimal('bid_increment', 15, 2)->default(1.00)->after('max_amount');
            $table->boolean('is_active')->default(true)->after('bid_increment');
        });
    }

    public function down(): void
    {
        Schema::table('auto_bids', function (Blueprint $table) {
            $table->dropColumn(['bid_increment', 'is_active']);
        });
    }
};
