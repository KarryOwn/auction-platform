<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->boolean('has_authenticity_cert')
                ->default(false)
                ->after('serial_number');
            $table->string('authenticity_cert_status', 20)
                ->default('none')
                ->after('has_authenticity_cert');
            $table->timestamp('authenticity_cert_verified_at')
                ->nullable()
                ->after('authenticity_cert_status');
            $table->foreignId('authenticity_cert_verified_by')
                ->nullable()
                ->after('authenticity_cert_verified_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('authenticity_cert_notes')
                ->nullable()
                ->after('authenticity_cert_verified_by');

            $table->index(
                ['has_authenticity_cert', 'authenticity_cert_status'],
                'auctions_auth_cert_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropIndex('auctions_auth_cert_lookup_idx');
            $table->dropConstrainedForeignId('authenticity_cert_verified_by');
            $table->dropColumn([
                'has_authenticity_cert',
                'authenticity_cert_status',
                'authenticity_cert_verified_at',
                'authenticity_cert_notes',
            ]);
        });
    }
};
