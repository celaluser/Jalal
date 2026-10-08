<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Campaigns can go out by SMS or WhatsApp and to a segment of guests, not only to everyone by e-mail.
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('channel', 10)->default('email')->after('name'); // email | sms | whatsapp
            $table->string('segment', 12)->default('all')->after('channel'); // all | new | regulars | vip | lapsed
        });

        // Happy hour: a percentage off for some days and hours, for the whole menu or one category.
        Schema::create('price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedTinyInteger('percent');
            $table->json('days')->nullable(); // 0 (Sunday) to 6; null = every day
            $table->string('from_time', 5)->default('00:00');
            $table->string('to_time', 5)->default('23:59');
            $table->unsignedBigInteger('category_id')->nullable(); // null = whole menu
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Gift cards: a code with a balance that is spent down across orders.
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 24)->unique();
            $table->unsignedInteger('initial_cents');
            $table->unsignedInteger('balance_cents');
            $table->string('recipient_email', 190)->nullable();
            $table->string('note', 200)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 0-10 "would you recommend us" question next to the stars.
        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedTinyInteger('nps')->nullable()->after('rating');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('winback_at')->nullable(); // last automatic "we miss you" message
        });
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('winback_at'));
        Schema::table('reviews', fn (Blueprint $t) => $t->dropColumn('nps'));
        Schema::dropIfExists('gift_cards');
        Schema::dropIfExists('price_rules');
        Schema::table('campaigns', fn (Blueprint $t) => $t->dropColumn(['channel', 'segment']));
    }
};
