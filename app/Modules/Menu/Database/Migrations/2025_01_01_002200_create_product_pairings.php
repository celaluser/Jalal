<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Goes well with": hand-picked suggestions shown when a dish is in the cart.
        Schema::create('product_pairings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('paired_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);

            $table->unique(['product_id', 'paired_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_pairings');
    }
};
