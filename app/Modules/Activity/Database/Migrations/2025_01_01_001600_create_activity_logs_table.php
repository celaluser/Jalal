<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->nullable()->index(); // null = platform-level action
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name', 120)->nullable();                     // kept even if the user is deleted later
            $table->string('event', 20);                                       // created | updated | deleted | custom action
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('label', 190)->nullable();
            $table->json('changes')->nullable();                               // {field: [old, new]}
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['restaurant_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
