<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('table_id')->nullable()->index();
            $table->string('token', 24)->unique();
            $table->string('name', 80);
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->unsignedSmallInteger('party_size');
            $table->timestamp('starts_at')->index();         // UTC
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('status', 10)->default('pending'); // pending | confirmed | seated | completed | cancelled | no_show
            $table->string('source', 6)->default('web');      // web | staff
            $table->string('note', 300)->nullable();
            $table->string('locale', 12)->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
