<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingPresence extends Model
{
    protected $fillable = ['user_id', 'reading_document_id', 'started_at', 'last_seen_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ReadingDocument::class, 'reading_document_id');
    }

    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('last_seen_at', '>=', now()->subMinutes(2));
    }
}
