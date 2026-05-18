<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('delivery_status', 30)->nullable()->after('is_closed');
            $table->timestamp('delivery_updated_at')->nullable()->after('delivery_status');
            $table->text('delivery_note')->nullable()->after('delivery_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_status',
                'delivery_updated_at',
                'delivery_note',
            ]);
        });
    }
};
