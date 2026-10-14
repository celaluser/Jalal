<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'categories'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->json('locked_locales')->nullable(); // languages a person wrote by hand: AI translation never touches them
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'categories'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->dropColumn('locked_locales'));
        }
    }
};
