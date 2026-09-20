<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolClass extends Model
{
    protected $guarded = [];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }
}
