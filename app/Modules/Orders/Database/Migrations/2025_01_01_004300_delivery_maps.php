<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->json('polygon')->nullable(); // [[lat, lng], ...] drawn on the map; null = a zone the guest picks from the list
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('delivery_lat', 10, 7)->nullable(); // where the guest said the food goes
            $table->decimal('delivery_lng', 10, 7)->nullable();
            $table->decimal('courier_lat', 10, 7)->nullable(); // last position the courier shared while delivering
            $table->decimal('courier_lng', 10, 7)->nullable();
            $table->timestamp('courier_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['delivery_lat', 'delivery_lng', 'courier_lat', 'courier_lng', 'courier_at']));
        Schema::table('restaurants', fn (Blueprint $t) => $t->dropColumn(['lat', 'lng']));
        Schema::table('delivery_zones', fn (Blueprint $t) => $t->dropColumn('polygon'));
    }
};
