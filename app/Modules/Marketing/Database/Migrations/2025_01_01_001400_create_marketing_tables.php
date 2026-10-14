<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // People who ordered, kept per restaurant. Created from orders; the guest's consent to marketing is explicit.
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('phone_key', 20)->nullable(); // digits only, to recognise the same number written differently
            $table->string('locale', 12)->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestamp('opted_in_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->timestamp('first_order_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'email']);
            $table->index(['restaurant_id', 'phone_key']);
            $table->index(['restaurant_id', 'last_order_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('restaurant_id')->constrained()->nullOnDelete();
            $table->boolean('marketing_opt_in')->default(false)->after('customer_email');
            $table->unsignedInteger('discount_cents')->default(0)->after('subtotal_cents');
            $table->string('promo_code', 40)->nullable()->after('discount_cents');
        });

        // The restaurant's own discount codes (not the platform's subscription coupons).
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('description', 160)->nullable();
            $table->string('type', 10); // percent | fixed
            $table->unsignedInteger('value'); // percent (1-100) or cents
            $table->unsignedInteger('min_order_cents')->default(0);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            // Loyalty rewards: bound to one customer and created by an order.
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('source_order_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['restaurant_id', 'code']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('author', 80)->nullable();
            $table->boolean('is_public')->default(true);
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['restaurant_id', 'created_at']);
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('subject', 160);
            $table->text('body');
            $table->unsignedInteger('min_orders')->default(0); // audience: at least this many orders
            $table->string('status', 12)->default('draft'); // draft | sending | sent
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status', 10)->default('queued'); // queued | sent | failed | skipped
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'customer_id']);
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->json('marketing_settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', fn (Blueprint $table) => $table->dropColumn('marketing_settings'));
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('promo_codes');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['marketing_opt_in', 'discount_cents', 'promo_code']);
        });
        Schema::dropIfExists('customers');
    }
};
