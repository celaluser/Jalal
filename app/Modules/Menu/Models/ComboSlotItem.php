<?php

namespace App\Modules\Menu\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A dish offered in a combo slot, with the extra it costs (0 for the included choices). Reached through its slot, which is tenant scoped. */
class ComboSlotItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price_delta' => 'decimal:2'];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ComboSlot::class, 'combo_slot_id');
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
