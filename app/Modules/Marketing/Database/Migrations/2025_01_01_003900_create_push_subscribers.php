<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guests who allowed offers from a restaurant in their browser. One row per browser; the permission itself is the consent.
        Schema::create('push_subscribers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->char('endpoint_hash', 64);
            $table->text('endpoint');
            $table->string('p256dh', 200);
            $table->string('auth', 100);
            $table->string('locale', 8)->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'endpoint_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscribers');
    }
};
