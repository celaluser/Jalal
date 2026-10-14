<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_price', 10, 2)->nullable();          // what a portion costs the kitchen: for profit reports
            $table->string('video_url', 500)->nullable();
            $table->json('gallery')->nullable();                      // extra photo media ids, in order
            $table->json('nutrition')->nullable();                    // protein, carbs, fat, fiber, sugar, sodium (per portion)
            $table->string('portion_size', 60)->nullable();
            $table->json('badges')->nullable();                       // new | popular | chef | limited
            $table->date('limited_until')->nullable();                // the "limited time" badge and the dish itself end here
            $table->json('schedule')->nullable();                     // {days: [1-7], from: "HH:MM", to: "HH:MM"}, null = always
            $table->json('order_types')->nullable();                  // null = every type; else any of dine_in, takeaway, delivery
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('icon', 16)->nullable();                   // an emoji shown next to the name
            $table->json('schedule')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['cost_price', 'video_url', 'gallery', 'nutrition', 'portion_size', 'badges', 'limited_until', 'schedule', 'order_types']));
        Schema::table('categories', fn (Blueprint $t) => $t->dropColumn(['icon', 'schedule']));
    }
};
