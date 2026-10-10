<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('method', 12);                      // cash | card | online
            $table->string('gateway', 24)->nullable();         // for online payments: stripe, mollie...
            $table->string('reference', 40)->nullable()->index(); // one per hosted checkout; a table payment spans several rows
            $table->string('transaction_id', 120)->nullable();
            $table->unsignedInteger('amount_cents');           // paid towards the bill
            $table->unsignedInteger('tip_cents')->default(0);  // on top of the bill
            $table->unsignedInteger('commission_cents')->default(0); // the platform's share of an online payment
            $table->timestamp('commission_billed_at')->nullable();
            $table->unsignedInteger('refunded_cents')->default(0);
            $table->string('status', 10)->default('paid');     // pending | paid | failed
            $table->string('note', 200)->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // staff who took it
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('paid_cents')->default(0);
            $table->unsignedInteger('tip_cents')->default(0);
            $table->unsignedInteger('refunded_cents')->default(0);
        });

        // Orders marked paid before payments were tracked count as fully paid.
        DB::table('orders')->whereNotNull('paid_at')->update(['paid_cents' => DB::raw('total_cents')]);
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['paid_cents', 'tip_cents', 'refunded_cents']));
        Schema::dropIfExists('order_payments');
    }
};
