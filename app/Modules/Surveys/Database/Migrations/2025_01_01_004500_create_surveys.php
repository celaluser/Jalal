<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('slug', 80);
            $table->text('intro')->nullable();
            $table->text('thank_you')->nullable();
            $table->json('questions');
            $table->boolean('is_active')->default(true);
            $table->boolean('after_order')->default(false); // offered on the guest's order page once the order is done
            $table->unsignedInteger('responses_count')->default(0);
            $table->timestamps();
            $table->unique(['restaurant_id', 'slug']);
        });

        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->json('answers');
            $table->string('locale', 12)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->unique(['survey_id', 'order_id']); // one answer per order (several without an order, as NULLs differ)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('surveys');
    }
};
