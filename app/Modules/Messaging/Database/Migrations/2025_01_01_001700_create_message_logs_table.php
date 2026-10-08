<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->nullable()->index();
            $table->string('channel', 10);            // sms | whatsapp
            $table->string('provider', 40);
            $table->string('recipient', 30);           // masked: only the last digits are kept
            $table->string('purpose', 40)->nullable();  // order_ready, reservation_reminder, campaign ...
            $table->string('status', 10);              // sent | failed | skipped
            $table->string('error', 200)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['restaurant_id', 'channel', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};
