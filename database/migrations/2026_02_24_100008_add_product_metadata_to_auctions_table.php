<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->string('condition', 20)->nullable()->after('status');
            $table->foreignId('brand_id')->nullable()->after('condition')->constrained('brands')->nullOnDelete();
            $table->string('sku', 100)->nullable()->after('brand_id');
            $table->string('serial_number', 100)->nullable()->after('sku');

            $table->index('condition');
            $table->index('brand_id');
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropIndex(['condition']);
            $table->dropIndex(['brand_id']);
            $table->dropIndex(['sku']);
            $table->dropColumn(['condition', 'brand_id', 'sku', 'serial_number']);
        });
    }
};
