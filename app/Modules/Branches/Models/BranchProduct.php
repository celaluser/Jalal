<?php

namespace App\Modules\Branches\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class BranchProduct extends Model
{
    use BelongsToRestaurant;

    protected $table = 'branch_product';

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_available' => 'boolean'];
    }
}
