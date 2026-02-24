<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value', 255);
            $table->timestamps();

            $table->unique(['auction_id', 'attribute_id']);
            $table->index(['attribute_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_attribute_values');
    }
};
