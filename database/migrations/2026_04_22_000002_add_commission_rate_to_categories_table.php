<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 4)
                ->nullable()
                ->after('sort_order');

            $table->index('commission_rate');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['commission_rate']);
            $table->dropColumn('commission_rate');
        });
    }
};
