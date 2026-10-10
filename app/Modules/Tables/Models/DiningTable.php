<?php

namespace App\Modules\Tables\Models;

use App\Modules\Activity\Support\LogsActivity;
use App\Modules\Core\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DiningTable extends Model
{
    use BelongsToRestaurant, LogsActivity;

    protected $table = 'dining_tables';

    protected $guarded = ['id', 'restaurant_id', 'token'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'seats' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $table): void {
            $table->token ??= static::newToken();
        });
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** 12 lowercase letters and digits: ~62 bits, safe to print and impossible to enumerate. */
    public static function newToken(): string
    {
        do {
            $token = Str::lower(Str::random(12));
        } while (static::allTenants()->where('token', $token)->exists());

        return $token;
    }

    public function regenerateToken(): void
    {
        $this->forceFill(['token' => static::newToken()])->save();
    }
}
