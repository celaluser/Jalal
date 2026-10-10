<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->string('name', 120);
            $table->string('slug', 80);
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('phone', 40)->nullable();
            $table->json('schedule')->nullable();       // opening hours: {days, from, to}; null = always open
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['restaurant_id', 'slug']);
        });

        // What a branch changes about a dish. A blank value means "same as the main menu".
        Schema::create('branch_product', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('is_available')->nullable();
            $table->unsignedInteger('stock_qty')->nullable(); // this branch's own portions, when it tracks them separately
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
        });

        foreach (['dining_tables', 'orders', 'users'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('branch_id')->nullable()->index(); // null: the whole restaurant (users) or no branch (older rows)
            });
        }
    }

    public function down(): void
    {
        foreach (['dining_tables', 'orders', 'users'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('branch_id'));
        }

        Schema::dropIfExists('branch_product');
        Schema::dropIfExists('branches');
    }
};
