<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_pages', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 12)->unique();
            $table->json('content');
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 12);
            $table->string('slug', 120);
            $table->string('title');
            $table->longText('body');
            $table->string('meta_description', 300)->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('in_footer')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['locale', 'slug']);
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 12);
            $table->string('slug', 150);
            $table->string('title');
            $table->string('excerpt', 400)->nullable();
            $table->longText('body');
            $table->string('cover_url')->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['locale', 'slug']);
            $table->index(['is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        foreach (['blog_posts', 'pages', 'landing_pages'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
