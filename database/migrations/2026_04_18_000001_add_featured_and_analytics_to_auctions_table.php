<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            if (!Schema::hasColumn('auctions', 'views_count')) {
                $table->integer('views_count')->default(0)->after('unique_bidder_count');
            }
            if (!Schema::hasColumn('auctions', 'unique_viewers_count')) {
                $table->integer('unique_viewers_count')->default(0)->after('views_count');
            }
            if (!Schema::hasColumn('auctions', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('status');
            }
            if (!Schema::hasColumn('auctions', 'featured_until')) {
                $table->timestamp('featured_until')->nullable()->after('is_featured');
            }
            if (!Schema::hasColumn('auctions', 'featured_position')) {
                $table->smallInteger('featured_position')->nullable()->after('featured_until');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn([
                'views_count',
                'unique_viewers_count',
                'featured_position',
                // 'is_featured' and 'featured_until' were added in an earlier migration, so be careful.
                // We'll leave them alone or just drop what we explicitly added.
            ]);
        });
    }
};
