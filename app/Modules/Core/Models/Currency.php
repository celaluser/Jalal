<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_default' => 'boolean', 'decimals' => 'integer'];
    }

    /**
     * Format an amount using this currency's symbol, position and separators.
     */
    public function format(float|int|string $amount): string
    {
        $number = number_format((float) $amount, $this->decimals, $this->decimal_separator, $this->thousands_separator);

        return $this->symbol_position === 'after'
            ? $number.' '.$this->symbol
            : $this->symbol.$number;
    }
}
