<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    protected $fillable = ['author_id', 'book_category_id', 'title', 'slug', 'isbn', 'description', 'cover_url', 'published_year', 'shelf_location', 'price', 'total_copies', 'available_copies', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'price' => 'integer'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BookCategory::class, 'book_category_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(BookPurchase::class);
    }

    public function readingDocuments(): HasMany
    {
        return $this->hasMany(ReadingDocument::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('available_copies', '>', 0);
    }
}
