<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;

/** One attempt to send an SMS or WhatsApp message. Used to count a restaurant's monthly use. */
class MessageLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
