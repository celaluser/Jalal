<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('vehicle', 80)->nullable();          // curbside pickup: car colour, model or plate
            $table->string('room', 30)->nullable();             // room service
            $table->timestamp('scheduled_for')->nullable()->index(); // pre-order for a later time
            $table->unsignedInteger('packaging_cents')->default(0);
            $table->unsignedBigInteger('tab_id')->nullable()->index(); // orders of one table sitting share a tab (id of the first one)
            $table->timestamp('dispatched_at')->nullable();     // delivery: left the kitchen with the courier
            $table->string('notify_channel', 10)->nullable();   // sms | whatsapp | push: how the guest asked to be told
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('station', 30)->nullable();          // kitchen, bar, ... at the time of ordering
            $table->json('reorder')->nullable();                // size, set-menu picks and option ids, to order the same again
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('station', 30)->nullable();
        });

        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedBigInteger('table_id')->nullable()->index();
            $table->string('table_name', 60)->nullable();
            $table->string('kind', 12);                         // waiter | bill | water | other
            $table->string('note', 120)->nullable();
            $table->string('status', 8)->default('open');       // open | done
            $table->timestamp('done_at')->nullable();
            $table->unsignedBigInteger('done_by')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedBigInteger('order_id')->index();
            $table->text('endpoint');
            $table->string('p256dh', 200);
            $table->string('auth', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('service_requests');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('station'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn(['station', 'reorder']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['vehicle', 'room', 'scheduled_for', 'packaging_cents', 'tab_id', 'dispatched_at', 'notify_channel']));
    }
};
