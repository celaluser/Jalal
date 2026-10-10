<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->string('status', 12)->default('open')->index(); // open | answered | closed
            $table->string('priority', 10)->default('normal');       // low | normal | high
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_staff')->default(false); // true = written by the platform's support team
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable(); // Markdown
            $table->string('level', 10)->default('info'); // info | success | warning
            $table->boolean('is_active')->default(true);
            $table->boolean('is_dismissible')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['announcements', 'ticket_replies', 'tickets'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
