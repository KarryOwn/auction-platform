<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('facebook_id')->nullable()->unique()->after('google_id');
            $table->string('github_id')->nullable()->unique()->after('facebook_id');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_google_id_unique');
            $table->dropIndex('users_facebook_id_unique');
            $table->dropIndex('users_github_id_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'facebook_id', 'github_id']);
        });

        // Fill null passwords before making the column non-nullable
        \Illuminate\Support\Facades\DB::table('users')
            ->whereNull('password')
            ->update(['password' => bcrypt('placeholder')]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
