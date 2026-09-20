<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingDocument extends Model
{
    protected $fillable = [
        'uploaded_by', 'book_id', 'title', 'description', 'private_path', 'original_filename',
        'mime_type', 'file_size', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function isReadableBy(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->book_id !== null && BookPurchase::query()
            ->where('user_id', $user->id)
            ->where('book_id', $this->book_id)
            ->exists()) {
            return true;
        }

        return $this->book_id !== null && Loan::query()
            ->where('user_id', $user->id)
            ->where('book_id', $this->book_id)
            ->whereIn('status', ['borrowed', 'overdue'])
            ->where('fee_status', 'paid')
            ->whereNull('returned_at')
            ->whereNotNull('access_expires_at')
            ->where('access_expires_at', '>', now())
            ->exists();
    }
}
