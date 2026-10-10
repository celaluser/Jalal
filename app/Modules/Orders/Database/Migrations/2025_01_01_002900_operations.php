<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('manual_discount_cents')->default(0);   // given by staff at the till, apart from promo codes
            $table->unsignedBigInteger('courier_id')->nullable()->index();  // user who delivers it
            $table->string('delivery_zone', 80)->nullable();                // zone name at the time of ordering
            $table->timestamp('escalated_at')->nullable();                  // owner was told nobody picked it up
        });

        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('low_stock_notified_at')->nullable();
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->string('name', 80);
            $table->decimal('fee', 10, 2)->default(0);
            $table->decimal('min_order', 10, 2)->default(0);
            $table->unsignedSmallInteger('eta_minutes')->nullable();       // extra time for far zones
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamp('opened_at');
            $table->integer('opening_cents')->default(0);                  // cash in the drawer at the start
            $table->timestamp('closed_at')->nullable()->index();
            $table->integer('closing_cents')->nullable();                  // cash counted at the end
            $table->integer('expected_cents')->nullable();                 // what should be there, from the payments
            $table->json('summary')->nullable();                           // totals by method, orders, tips: frozen at closing
            $table->string('note', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('kind', 10);                                    // kitchen | receipt
            $table->longText('payload');                                   // base64 ESC/POS bytes
            $table->string('status', 8)->default('pending');               // pending | sent | done | failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('delivery_zones');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('low_stock_notified_at'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['manual_discount_cents', 'courier_id', 'delivery_zone', 'escalated_at']));
    }
};
