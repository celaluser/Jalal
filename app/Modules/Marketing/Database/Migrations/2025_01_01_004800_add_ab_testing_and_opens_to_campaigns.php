<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->boolean('ab_test')->default(false);
            $table->string('subject_b', 160)->nullable();
            $table->unsignedTinyInteger('test_percent')->default(20);   // share of the audience that gets the test, half A and half B
            $table->unsignedTinyInteger('winner_hours')->default(4);    // how long to wait before the better subject goes to everyone else
            $table->string('winner', 1)->nullable();
            $table->dateTime('test_sent_at')->nullable();
            $table->boolean('track_opens')->default(true);
        });

        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->string('variant', 1)->nullable();
            $table->dateTime('opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('campaign_recipients', fn (Blueprint $t) => $t->dropColumn(['variant', 'opened_at', 'open_count']));
        Schema::table('campaigns', fn (Blueprint $t) => $t->dropColumn(['ab_test', 'subject_b', 'test_percent', 'winner_hours', 'winner', 'test_sent_at', 'track_opens']));
    }
};
