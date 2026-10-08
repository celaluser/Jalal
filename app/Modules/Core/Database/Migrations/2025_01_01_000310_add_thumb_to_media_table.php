<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->boolean('has_thumb')->default(false); // a 480px copy exists next to the file
        });
    }

    public function down(): void
    {
        Schema::table('media', fn (Blueprint $table) => $table->dropColumn('has_thumb'));
    }
};
