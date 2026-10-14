<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('sender', 10); // guest | staff
            $table->unsignedBigInteger('user_id')->nullable(); // the staff member who wrote it
            $table->string('body', 500);
            $table->dateTime('staff_read_at')->nullable();
            $table->dateTime('guest_read_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->index(['order_id', 'id']);
            $table->index(['restaurant_id', 'sender', 'staff_read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
