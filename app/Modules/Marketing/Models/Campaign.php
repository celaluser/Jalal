<?php

namespace App\Modules\Marketing\Models;

use App\Modules\Activity\Support\LogsActivity;
use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use BelongsToRestaurant, LogsActivity;

    public const DRAFT = 'draft';

    public const SENDING = 'sending';

    public const TESTING = 'testing';

    public const SENT = 'sent';

    protected $guarded = ['id', 'restaurant_id', 'status', 'recipients_count', 'sent_count', 'skipped_count', 'sent_at', 'winner', 'test_sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'test_sent_at' => 'datetime', 'min_orders' => 'integer', 'ab_test' => 'boolean', 'track_opens' => 'boolean', 'test_percent' => 'integer', 'winner_hours' => 'integer'];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }
}
