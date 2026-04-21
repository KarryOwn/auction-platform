<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('return_policy_type', 30)->default('no_returns')->after('seller_bio');
            $table->unsignedInteger('return_window_days')->nullable()->after('return_policy_type');
            $table->text('return_policy_custom')->nullable()->after('return_window_days');
        });

        Schema::table('auctions', function (Blueprint $table) {
            $table->string('return_policy_override', 30)->nullable()->after('buy_it_now_enabled');
            $table->text('return_policy_custom_override')->nullable()->after('return_policy_override');
            $table->text('effective_return_policy_snapshot')->nullable()->after('return_policy_custom_override');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn([
                'return_policy_override',
                'return_policy_custom_override',
                'effective_return_policy_snapshot'
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'return_policy_type',
                'return_window_days',
                'return_policy_custom'
            ]);
        });
    }
};
