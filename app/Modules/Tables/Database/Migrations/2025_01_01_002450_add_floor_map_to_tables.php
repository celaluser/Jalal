<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            // Position on the floor map in tenths of a percent (0-1000), so it scales to any screen.
            $table->unsignedSmallInteger('map_x')->nullable();
            $table->unsignedSmallInteger('map_y')->nullable();
            $table->string('shape', 8)->default('square'); // square | round | wide
        });
    }

    public function down(): void
    {
        Schema::table('dining_tables', fn (Blueprint $table) => $table->dropColumn(['map_x', 'map_y', 'shape']));
    }
};
