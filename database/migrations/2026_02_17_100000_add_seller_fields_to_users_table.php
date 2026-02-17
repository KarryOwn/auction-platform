<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('seller_verified_at')->nullable()->after('ban_reason');
            $table->string('seller_application_status', 20)->default('none')->after('seller_verified_at');
            $table->text('seller_application_note')->nullable()->after('seller_application_status');
            $table->text('seller_bio')->nullable()->after('seller_application_note');
            $table->string('seller_avatar_path')->nullable()->after('seller_bio');
            $table->string('seller_slug')->nullable()->unique()->after('seller_avatar_path');
            $table->timestamp('seller_applied_at')->nullable()->after('seller_slug');
            $table->text('seller_rejected_reason')->nullable()->after('seller_applied_at');

            $table->index('seller_application_status');
            $table->index('seller_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['seller_application_status']);
            $table->dropIndex(['seller_verified_at']);
            $table->dropUnique(['seller_slug']);

            $table->dropColumn([
                'seller_verified_at',
                'seller_application_status',
                'seller_application_note',
                'seller_bio',
                'seller_avatar_path',
                'seller_slug',
                'seller_applied_at',
                'seller_rejected_reason',
            ]);
        });
    }
};
