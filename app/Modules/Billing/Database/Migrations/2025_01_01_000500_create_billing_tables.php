<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('interval', 12); // monthly | yearly | lifetime | free
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency_code', 8)->default('USD');
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->json('limits')->nullable();   // branches, tables, products, categories, staff, ai_credits (null = unlimited)
            $table->json('features')->nullable(); // custom_domain, whatsapp_orders, online_payments, reservations, analytics, remove_branding
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type', 10); // percent | fixed
            $table->decimal('value', 10, 2);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('plan_ids')->nullable(); // null = every plan
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('status', 12)->index(); // trialing | active | past_due | canceled | expired
            $table->string('interval', 12);
            $table->decimal('price', 10, 2)->default(0); // snapshot: later plan price changes do not touch it
            $table->string('currency_code', 8)->default('USD');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->string('gateway', 40)->nullable();
            $table->string('gateway_ref')->nullable();
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->timestamps();
            $table->index(['restaurant_id', 'status']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete(); // what paying this invoice buys
            $table->string('number')->unique();
            $table->string('status', 12)->index(); // open | paid | void | refunded
            $table->string('currency_code', 8);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('tax_name', 40)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('coupon_code')->nullable();
            $table->json('items');
            $table->json('billing')->nullable(); // seller + buyer snapshot printed on the PDF
            $table->string('gateway', 40)->nullable();
            $table->string('gateway_ref')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('gateway', 40);
            $table->string('transaction_id');       // the gateway's own id for this payment
            $table->unsignedBigInteger('amount');   // minor units (cents)
            $table->string('currency_code', 8);
            $table->string('status', 12);           // pending | succeeded | failed | mismatch
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'transaction_id']); // makes webhook replays idempotent
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['coupon_redemptions', 'payments', 'invoices', 'subscriptions', 'coupons', 'plans'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
