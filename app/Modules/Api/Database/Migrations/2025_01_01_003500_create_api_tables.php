<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('prefix', 12);                // first characters, so the owner can recognise a token
            $table->string('token_hash', 64)->unique();  // sha256 of the token; the token itself is shown once
            $table->json('abilities');                   // menu:read, menu:write, orders:read, orders:write
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->text('secret');                      // encrypted; signs every delivery
            $table->json('events');                      // order.created, order.status_changed, order.paid or *
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('failures')->default(0); // in a row; the endpoint is switched off at the limit
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('uuid', 36)->unique();
            $table->string('event', 40);
            $table->json('payload');
            $table->string('status', 10)->default('pending'); // pending | sent | failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->string('error', 200)->nullable();
            $table->timestamps();

            $table->index(['webhook_endpoint_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('api_tokens');
    }
};
