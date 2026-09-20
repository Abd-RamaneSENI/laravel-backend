<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = ['user_id', 'book_id', 'reserved_at', 'expires_at', 'fulfilled_at', 'status'];

    protected function casts(): array
    {
        return ['reserved_at' => 'datetime', 'expires_at' => 'datetime', 'fulfilled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
