<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One counter row per restaurant, hour, table and kind (never one row per visit, never anything about the visitor).
        Schema::create('menu_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id');
            $table->date('day');
            $table->unsignedTinyInteger('hour');
            $table->unsignedBigInteger('table_id')->default(0);      // 0 = not from a table QR code
            $table->string('kind', 5);                               // scan (table QR code) | view (menu opened)
            $table->unsignedInteger('count')->default(0);
            $table->unique(['restaurant_id', 'day', 'hour', 'table_id', 'kind'], 'menu_visits_unique');
            $table->index(['restaurant_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_visits');
    }
};
