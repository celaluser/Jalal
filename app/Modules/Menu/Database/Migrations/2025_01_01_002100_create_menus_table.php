<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Breakfast", "Lunch", "Drinks", "Wine list": groups of categories with their own opening hours.
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->json('name');
            $table->json('schedule')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('menu_id')->nullable()->index(); // null = the main menu, always on
        });
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $t) => $t->dropColumn('menu_id'));
        Schema::dropIfExists('menus');
    }
};
