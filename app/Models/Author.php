<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Author extends Model
{
    protected $fillable = ['name', 'slug', 'biography'];

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}
