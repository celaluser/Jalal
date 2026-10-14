<?php

namespace App\Modules\Surveys\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class SurveyResponse extends Model
{
    use BelongsToRestaurant;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'restaurant_id'];

    protected function casts(): array
    {
        return ['answers' => 'array', 'created_at' => 'datetime'];
    }
}
