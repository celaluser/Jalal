<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 20)->default('active')->index();
            $table->string('subdomain')->nullable()->unique();
            $table->string('custom_domain')->nullable()->unique();
            $table->timestamp('domain_verified_at')->nullable();
            $table->string('locale', 12)->default('en');
            $table->string('currency_code', 8)->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('theme', 40)->default('default');
            $table->json('branding')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('restaurant_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('restaurant_id');
        });

        Schema::dropIfExists('restaurants');
    }
};
