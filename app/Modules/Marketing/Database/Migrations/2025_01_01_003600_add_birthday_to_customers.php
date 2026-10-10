<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Only day and month, never the year: enough for a birthday treat, less personal data to protect.
            $table->unsignedTinyInteger('birth_month')->nullable();
            $table->unsignedTinyInteger('birth_day')->nullable();
            $table->unsignedSmallInteger('birthday_year')->nullable(); // the year the last birthday treat was sent
        });
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn(['birth_month', 'birth_day', 'birthday_year']));
    }
};
