<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Old MySQL (5.7) and MariaDB (10.3) turn the first NOT NULL TIMESTAMP column of a table into one that rewrites itself with the
 * current time on every update (unless the server setting explicit_defaults_for_timestamp is on). Reservation times, rental start
 * dates, invoice dates and shift opening times would have moved whenever a row was saved. New installs create these columns as
 * DATETIME; this brings installs made earlier in line. Harmless where the column already is DATETIME.
 */
return new class extends Migration
{
    private const COLUMNS = [['invoices', 'issued_at'], ['shifts', 'opened_at'], ['reservations', 'starts_at'], ['store_entitlements', 'starts_at']];

    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (self::COLUMNS as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` DATETIME NOT NULL");
            }
        }
    }

    public function down(): void
    {
        //
    }
};
