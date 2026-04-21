<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
            $table->boolean('is_deactivated')->default(false)->after('is_banned');
            $table->timestamp('deactivated_at')->nullable()->after('is_deactivated');
            $table->timestamp('reactivation_deadline')->nullable()->after('deactivated_at');
            $table->index('is_deactivated');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropIndex(['is_deactivated']);
            $table->dropColumn(['is_deactivated', 'deactivated_at', 'reactivation_deadline']);
        });
    }
};
