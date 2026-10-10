<?php

namespace App\Modules\Store\Models;

use Illuminate\Database\Eloquent\Model;

/** Platform-level override of one store item. Not tenant data. */
class StoreItem extends Model
{
    protected $table = 'store_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price' => 'float', 'visible' => 'boolean'];
    }
}
