<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('referral_code', 12)->nullable()->unique();
            $table->decimal('billing_credit', 10, 2)->default(0); // earned by referring; used up on the next invoices
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('credit_used', 10, 2)->default(0);
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('referred_id')->unique()->constrained('restaurants')->cascadeOnDelete();
            $table->string('status', 10)->default('pending'); // pending | rewarded
            $table->decimal('reward', 10, 2)->default(0);
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('credit_used'));
        Schema::table('restaurants', fn (Blueprint $table) => $table->dropColumn(['referral_code', 'billing_credit']));
    }
};
