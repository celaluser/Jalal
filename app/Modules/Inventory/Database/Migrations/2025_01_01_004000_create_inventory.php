<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->string('name', 120);
            $table->string('phone', 40)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('name', 120);
            $table->string('unit', 12)->default('kg');
            $table->decimal('stock_qty', 14, 3)->default(0);
            $table->decimal('low_at', 14, 3)->nullable();
            $table->decimal('unit_cost', 14, 4)->nullable(); // moving average of what was paid per unit
            $table->timestamps();
        });

        Schema::create('recipe_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('ingredient_id')->index();
            $table->decimal('qty', 14, 3); // of the ingredient's unit, per portion
            $table->timestamps();

            $table->unique(['product_id', 'ingredient_id']);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedBigInteger('ingredient_id')->index();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->decimal('qty', 14, 3);
            $table->decimal('unit_cost', 14, 4);
            $table->date('purchased_on');
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['purchases', 'recipe_lines', 'ingredients', 'suppliers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
