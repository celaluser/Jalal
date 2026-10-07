<?php

namespace App\Modules\Marketing\Models;

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use BelongsToRestaurant;

    public const DRAFT = 'draft';

    public const SENDING = 'sending';

    public const SENT = 'sent';

    protected $guarded = ['id', 'restaurant_id', 'status', 'recipients_count', 'sent_count', 'skipped_count', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'min_orders' => 'integer'];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }
}
