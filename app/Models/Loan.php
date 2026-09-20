<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Loan extends Model
{
    public const BORROWING_FEE_RATE = 0.05;
    public const READING_ACCESS_DAYS = 30;

    protected $fillable = [
        'user_id', 'book_id', 'loaned_by', 'returned_by', 'borrowed_at', 'due_at', 'returned_at', 'hidden_by_user_at', 'status',
        'fee_amount', 'fee_charged_amount', 'fee_refund_amount', 'fee_refund_status', 'fee_refunded_at',
        'fee_currency', 'fee_status', 'fee_paid_at', 'access_expires_at', 'fee_provider', 'fee_payment_reference',
    ];

    protected function casts(): array
    {
        return [
            'borrowed_at' => 'datetime',
            'due_at' => 'datetime',
            'returned_at' => 'datetime',
            'hidden_by_user_at' => 'datetime',
            'fee_paid_at' => 'datetime',
            'access_expires_at' => 'datetime',
            'fee_refunded_at' => 'datetime',
            'fee_amount' => 'integer',
            'fee_charged_amount' => 'integer',
            'fee_refund_amount' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function lender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'loaned_by');
    }

    public function feeOrder(): HasOne
    {
        return $this->hasOne(Order::class)->where('purpose', 'loan_borrowing_fee');
    }

    public static function borrowingFeeFor(Book $book): int
    {
        return (int) ceil(max(0, (int) $book->price) * self::BORROWING_FEE_RATE);
    }

    public function hasPaidBorrowingFee(): bool
    {
        return $this->fee_status === 'paid';
    }

    public function hasActiveReadingAccess(): bool
    {
        return $this->status !== 'returned'
            && $this->returned_at === null
            && $this->fee_status === 'paid'
            && $this->access_expires_at !== null
            && $this->access_expires_at->isFuture();
    }

    public function billableDaysUsed(): int
    {
        if (! $this->fee_paid_at) {
            return 0;
        }

        return (int) min(
            self::READING_ACCESS_DAYS,
            max(0, $this->fee_paid_at->copy()->startOfDay()->diffInDays(now()->startOfDay()))
        );
    }

    public function chargedAmountForReturn(): int
    {
        if ($this->fee_amount <= 0) {
            return 0;
        }

        return (int) ceil($this->fee_amount * $this->billableDaysUsed() / self::READING_ACCESS_DAYS);
    }
}
