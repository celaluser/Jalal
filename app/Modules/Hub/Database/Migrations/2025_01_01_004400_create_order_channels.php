<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('kind', 30)->default('generic'); // which adapter reads the incoming data
            $table->string('token_hash', 64)->unique();      // the address secret is shown once; only its hash is kept
            $table->string('token_hint', 8);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('orders_count')->default(0);
            $table->timestamp('last_received_at')->nullable();
            $table->string('last_error', 190)->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('channel_id')->nullable()->index();
            $table->string('external_id', 80)->nullable(); // the other platform's own order number, so a repeated delivery is not a second order
            $table->unique(['channel_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['channel_id', 'external_id']);
            $table->dropColumn(['channel_id', 'external_id']);
        });
        Schema::dropIfExists('order_channels');
    }
};
