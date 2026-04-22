<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_featured')
                ->default(false)
                ->after('is_active');
            $table->timestamp('featured_until')
                ->nullable()
                ->after('is_featured');
            $table->unsignedInteger('featured_sort_order')
                ->default(0)
                ->after('featured_until');
            $table->string('featured_banner_path')
                ->nullable()
                ->after('featured_sort_order');
            $table->string('featured_tagline', 200)
                ->nullable()
                ->after('featured_banner_path');

            $table->index(['is_featured', 'featured_sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['is_featured', 'featured_sort_order']);
            $table->dropColumn([
                'is_featured',
                'featured_until',
                'featured_sort_order',
                'featured_banner_path',
                'featured_tagline',
            ]);
        });
    }
};
