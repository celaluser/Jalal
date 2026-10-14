<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The card a restaurant chose to keep for automatic renewal. Only the gateway's own ids and what is printed on a receipt
        // (brand, last four digits, expiry) are stored: never the card number.
        Schema::create('saved_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('gateway', 24);
            $table->string('customer_id', 80);
            $table->string('method_id', 80);
            $table->string('brand', 20)->nullable();
            $table->string('last4', 4)->nullable();
            $table->unsignedTinyInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->dateTime('last_attempt_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_cards');
    }
};
