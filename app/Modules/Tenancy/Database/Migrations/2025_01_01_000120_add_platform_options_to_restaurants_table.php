<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What the platform owner decides about a restaurant: showcase flags, badge, custom limits and features, a locked theme, a private note. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_featured')->default(false)->index();
            $table->string('badge', 30)->nullable();
            $table->unsignedSmallInteger('featured_order')->default(0);
            $table->boolean('is_limited')->default(false);   // custom limits switched on
            $table->json('limit_overrides')->nullable();      // key => number, or "unlimited"; a missing key follows the plan
            $table->json('feature_overrides')->nullable();    // key => true|false; a missing key follows the plan
            $table->boolean('theme_locked')->default(false);  // the owner cannot change the menu theme
            $table->text('admin_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['is_premium', 'is_featured', 'badge', 'featured_order', 'is_limited', 'limit_overrides', 'feature_overrides', 'theme_locked', 'admin_notes']);
        });
    }
};
