<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->json('title');                       // per language
            $table->json('text')->nullable();
            $table->json('button')->nullable();          // button label per language
            $table->string('link_url', 500)->nullable(); // https link, or a path on the menu
            $table->unsignedBigInteger('image_media_id')->nullable();
            $table->boolean('is_popup')->default(false); // shown once as a pop-up instead of in the banner strip
            $table->boolean('is_active')->default(true);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
