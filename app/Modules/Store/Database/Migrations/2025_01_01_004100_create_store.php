<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the platform changed about a store item (price, free or paid, texts). Items nobody touched have no row.
        Schema::create('store_items', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique(); // feature:custom_domain, theme:aurora
            $table->string('mode', 8)->default('plan'); // free | plan | paid
            $table->string('billing', 10)->default('monthly'); // one_time | monthly | yearly
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency_code', 8)->nullable();
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->string('name', 80)->nullable();
            $table->string('summary', 200)->nullable();
            $table->text('description')->nullable();
            $table->boolean('visible')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        // What a restaurant owns: bought, tried or granted by the platform.
        Schema::create('store_entitlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->string('item_slug', 80);
            $table->string('status', 10)->default('active'); // active | expired | revoked
            $table->string('source', 10)->default('purchase'); // purchase | trial | admin
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable(); // null = for good
            $table->unsignedBigInteger('invoice_id')->nullable()->index();
            $table->timestamps();

            $table->index(['restaurant_id', 'item_slug']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('store_slug', 80)->nullable()->after('plan_id'); // set when the invoice buys a store item instead of a plan
            $table->unsignedSmallInteger('store_months')->nullable()->after('store_slug');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn(['store_slug', 'store_months']));
        Schema::dropIfExists('store_entitlements');
        Schema::dropIfExists('store_items');
    }
};
