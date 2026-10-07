<?php

namespace App\Modules\Tables\Database\Seeders;

use App\Modules\Tables\Models\Area;
use App\Modules\Tables\Models\DiningTable;
use Illuminate\Database\Seeder;

/** Two areas and a handful of tables for each demo restaurant, so QR codes can be tried right away. */
class TablesDemoSeeder extends Seeder
{
    public function run(): void
    {
        DiningTable::query()->delete();
        Area::query()->delete();

        $inside = Area::create(['name' => 'Main hall', 'sort' => 1]);
        $outside = Area::create(['name' => 'Terrace', 'sort' => 2]);

        foreach (range(1, 8) as $n) {
            DiningTable::create(['name' => "Table {$n}", 'area_id' => $n <= 5 ? $inside->id : $outside->id, 'seats' => $n % 2 ? 4 : 2, 'sort' => $n]);
        }
    }
}
