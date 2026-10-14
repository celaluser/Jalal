<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->string('applies_to', 8)->default('plans')->after('type'); // plans | store | all
            $table->json('store_slugs')->nullable()->after('plan_ids'); // null = every store item
        });

        Schema::table('store_entitlements', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable(); // set when the "ends soon" e-mail went out; cleared when the item is extended
        });

        Schema::table('store_items', function (Blueprint $table) {
            $table->json('bundle_items')->nullable(); // for slug bundle:...: the item slugs it contains
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->timestamp('low_notified_at')->nullable(); // one low-stock e-mail per dip, like dishes
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', fn (Blueprint $t) => $t->dropColumn('low_notified_at'));
        Schema::table('store_items', fn (Blueprint $t) => $t->dropColumn('bundle_items'));
        Schema::table('store_entitlements', fn (Blueprint $t) => $t->dropColumn('reminded_at'));
        Schema::table('coupons', fn (Blueprint $t) => $t->dropColumn(['applies_to', 'store_slugs']));
    }
};
