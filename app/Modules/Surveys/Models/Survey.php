<?php

namespace App\Modules\Surveys\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A short questionnaire a restaurant shares by link, QR code or after an order. Questions are kept as a list: rating, nps, choice or text. */
class Survey extends Model
{
    use BelongsToRestaurant;

    public const TYPES = ['rating', 'nps', 'choice', 'text'];

    public const MAX_QUESTIONS = 12;

    protected $guarded = ['id', 'restaurant_id', 'responses_count'];

    protected function casts(): array
    {
        return ['questions' => 'array', 'is_active' => 'boolean', 'after_order' => 'boolean'];
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }
}
