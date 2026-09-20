<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['signature_valid' => 'boolean', 'received_at' => 'datetime', 'processed_at' => 'datetime'];
    }
}
