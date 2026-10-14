<?php

namespace App\Modules\Support\Models;

use App\Models\User;
use App\Modules\Core\Tenancy\BelongsToRestaurant;
use App\Modules\Core\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use BelongsToRestaurant;

    public const STATUSES = ['open', 'answered', 'closed'];

    public const PRIORITIES = ['low', 'normal', 'high'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_reply_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->withoutGlobalScope(TenantScope::class)->oldest('id');
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
