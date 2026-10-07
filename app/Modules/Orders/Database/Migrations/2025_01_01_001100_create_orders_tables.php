<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ordering rules of the restaurant (see OrderSettings): which order types, tax, fees, minimums.
        Schema::table('restaurants', function (Blueprint $table) {
            $table->json('order_settings')->nullable()->after('menu_locales');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedInteger('number'); // running number per restaurant, shown to staff and guests
            $table->string('token', 32)->unique(); // unguessable key of the public tracking page
            $table->string('idempotency_key', 64)->nullable(); // a double tap must not create two orders
            $table->string('type', 12); // dine_in | takeaway | delivery
            $table->string('status', 12)->default('new')->index();
            $table->string('source', 8)->default('qr'); // qr | staff
            $table->unsignedBigInteger('table_id')->nullable()->index();
            $table->string('table_name', 60)->nullable(); // kept even if the table is renamed or deleted
            $table->string('customer_name', 80)->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->string('delivery_address', 255)->nullable();
            $table->string('note', 300)->nullable();
            $table->string('locale', 12)->nullable();
            $table->char('currency_code', 3)->nullable();
            // Money is stored in cents so sums are exact.
            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->unsignedInteger('service_cents')->default(0);
            $table->unsignedInteger('delivery_cents')->default(0);
            $table->unsignedInteger('tax_cents')->default(0);
            $table->unsignedInteger('total_cents')->default(0);
            $table->string('payment_method', 12)->nullable(); // cash | card (settled on the spot)
            $table->timestamp('paid_at')->nullable();
            $table->string('cancel_reason', 200)->nullable();
            $table->unsignedSmallInteger('prep_minutes')->nullable(); // promised preparation time
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'number']);
            $table->unique(['restaurant_id', 'idempotency_key']);
            $table->index(['restaurant_id', 'status', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('product_id')->nullable(); // may disappear from the menu later
            // Snapshot of what was sold: later menu edits never rewrite past orders.
            $table->string('name');
            $table->json('options')->nullable(); // [{group, name, price_delta_cents}]
            $table->string('note', 200)->nullable();
            $table->unsignedSmallInteger('qty');
            $table->unsignedInteger('unit_cents');
            $table->unsignedInteger('total_cents');
            $table->timestamps();
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('user_id')->nullable(); // null: the guest or the system
            $table->string('type', 20); // placed | status | payment | note
            $table->string('from', 12)->nullable();
            $table->string('to', 12)->nullable();
            $table->string('note', 200)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');

        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('order_settings');
        });
    }
};
