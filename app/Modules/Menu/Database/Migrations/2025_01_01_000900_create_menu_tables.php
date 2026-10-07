<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Languages the restaurant's customers can switch between (the default language is always included).
        Schema::table('restaurants', function (Blueprint $table) {
            $table->json('menu_locales')->nullable()->after('locale');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->json('name');
            $table->json('description')->nullable();
            $table->unsignedBigInteger('image_media_id')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['restaurant_id', 'sort']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedBigInteger('category_id')->index();
            $table->json('name');
            $table->json('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('compare_price', 10, 2)->nullable(); // the old price, shown struck through
            $table->unsignedBigInteger('image_media_id')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);      // visible on the menu at all
            $table->boolean('is_available')->default(true);   // false = "sold out today", still listed
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('calories')->nullable();
            $table->unsignedSmallInteger('prep_minutes')->nullable();
            $table->json('allergens')->nullable();
            $table->json('dietary')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'category_id', 'sort']);
        });

        // Reusable "options & extras" groups: sizes, toppings, sauces ...
        Schema::create('option_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->json('name');
            $table->string('type', 10)->default('single'); // single (radio) | multiple (checkboxes)
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('max_select')->nullable(); // multiple only; null = no cap
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedBigInteger('option_group_id')->index();
            $table->json('name');
            $table->decimal('price_delta', 10, 2)->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('option_group_product', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('option_group_id');
            $table->unsignedInteger('sort')->default(0);

            $table->primary(['product_id', 'option_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('option_group_product');
        Schema::dropIfExists('options');
        Schema::dropIfExists('option_groups');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');

        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('menu_locales');
        });
    }
};
