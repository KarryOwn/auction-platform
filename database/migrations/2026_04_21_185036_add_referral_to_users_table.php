<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 12)->nullable()->unique()->after('seller_slug');
            $table->foreignId('referred_by_user_id')->nullable()->after('referral_code')
                  ->constrained('users')->nullOnDelete();
            $table->index('referral_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by_user_id']);
            $table->dropIndex(['referral_code']);
            $table->dropColumn(['referral_code', 'referred_by_user_id']);
        });
    }
};
