<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // null = stock is not tracked (always available unless switched off by hand)
            $table->unsignedInteger('stock_qty')->nullable()->after('is_featured');
            $table->unsignedInteger('low_stock_at')->nullable()->after('stock_qty'); // warn when this few are left
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['stock_qty', 'low_stock_at']));
    }
};
