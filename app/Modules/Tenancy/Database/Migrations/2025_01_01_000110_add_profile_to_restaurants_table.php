<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('phone', 40)->nullable()->after('name');
            $table->string('address', 255)->nullable()->after('phone');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('country', 100)->nullable()->after('city');
            $table->unsignedBigInteger('logo_media_id')->nullable()->after('branding');
            // Set when the owner finishes (or skips) the setup wizard.
            $table->timestamp('onboarded_at')->nullable()->after('trial_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['phone', 'address', 'city', 'country', 'logo_media_id', 'onboarded_at']);
        });
    }
};
