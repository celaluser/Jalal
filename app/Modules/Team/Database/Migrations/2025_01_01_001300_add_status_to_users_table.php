<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Set when an owner switches a staff account off: the person cannot sign in, history stays.
            $table->timestamp('disabled_at')->nullable()->after('email_verified_at');
            // Set when the account was created by an invitation; cleared once they chose a password.
            $table->timestamp('invited_at')->nullable()->after('disabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['disabled_at', 'invited_at']);
        });
    }
};
