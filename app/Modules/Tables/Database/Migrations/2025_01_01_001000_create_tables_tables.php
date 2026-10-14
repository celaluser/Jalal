<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dining areas: "Terrace", "Main hall", "Bar".
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->string('name', 80);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedBigInteger('area_id')->nullable()->index();
            $table->string('name', 60);
            $table->unsignedSmallInteger('seats')->nullable();
            // Random public identifier printed into the QR code. Unguessable, so a customer cannot
            // walk through other tables by editing the URL; regenerating it retires an old QR.
            $table->string('token', 16)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['restaurant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('areas');
    }
};
