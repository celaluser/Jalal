<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            // Units of this currency per 1 unit of a common reference (set by the platform admin).
            // Used only to show guests an approximate price in another currency; orders are always charged in the restaurant's own.
            $table->decimal('rate', 18, 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('currencies', fn (Blueprint $table) => $table->dropColumn('rate'));
    }
};
