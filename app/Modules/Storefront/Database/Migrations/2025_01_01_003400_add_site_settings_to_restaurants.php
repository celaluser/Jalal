<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->json('site_settings')->nullable(); // mini website and SEO options
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', fn (Blueprint $t) => $t->dropColumn('site_settings'));
    }
};
