<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedInteger('deposit_cents')->default(0);
            // none | pending | paid | applied (guest came, deposit counts towards the bill) | refunded | refund_due (pay back by hand) | forfeited (no-show) | failed
            $table->string('deposit_status', 10)->default('none');
            $table->string('deposit_gateway', 24)->nullable();
            $table->string('deposit_reference', 40)->nullable()->index();
            $table->string('deposit_transaction', 120)->nullable();
            $table->timestamp('deposit_paid_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', fn (Blueprint $t) => $t->dropColumn(['deposit_cents', 'deposit_status', 'deposit_gateway', 'deposit_reference', 'deposit_transaction', 'deposit_paid_at']));
    }
};
