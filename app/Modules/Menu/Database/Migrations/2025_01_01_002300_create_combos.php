<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_combo')->default(false); // a set menu: pick one dish per slot (main, side, drink)
        });

        Schema::create('combo_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete(); // the combo
            $table->json('name');                                               // "Choose your drink"
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('combo_slot_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combo_slot_id')->constrained('combo_slots')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete(); // the dish offered in that slot
            $table->decimal('price_delta', 10, 2)->default(0);
            $table->unsignedInteger('sort')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_slot_items');
        Schema::dropIfExists('combo_slots');
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('is_combo'));
    }
};
