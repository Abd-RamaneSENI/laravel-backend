<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Loan;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        $query = Loan::query()->with($this->loanRelations($request->user()))->latest('borrowed_at');
        if (! $request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id)
                ->whereNull('hidden_by_user_at');
        }

        return response()->json($query->paginate(min(max($request->integer('per_page', 20), 1), 50)));
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'reservation_id' => ['nullable', 'integer', 'exists:reservations,id'],
        ]);
        $loan = $this->createLoan($request->user(), $data['book_id'], $request->user()->id, Loan::READING_ACCESS_DAYS, $data['reservation_id'] ?? null);
        $audit->record($request, 'loan.created', $loan, ['book_id' => $loan->book_id]);

        return response()->json(['loan' => $loan->load($this->loanRelations($request->user()))], 201);
    }

    public function storeForMember(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'due_days' => ['nullable', 'integer', 'between:1,'.Loan::READING_ACCESS_DAYS],
            'reservation_id' => ['nullable', 'integer', 'exists:reservations,id'],
        ]);
        $member = User::query()->where('is_active', true)->findOrFail($data['user_id']);
        $loan = $this->createLoan($member, $data['book_id'], $request->user()->id, $data['due_days'] ?? Loan::READING_ACCESS_DAYS, $data['reservation_id'] ?? null);
        $audit->record($request, 'loan.created_by_admin', $loan, ['book_id' => $loan->book_id, 'member_id' => $member->id]);

        return response()->json(['loan' => $loan->load($this->loanRelations($request->user()))], 201);
    }

    public function payFee(Request $request, Loan $loan, AuditLogger $audit)
    {
        abort_unless($loan->user_id === $request->user()->id, 403);
        abort_if($loan->returned_at || $loan->status === 'returned', 422, 'Cet emprunt est déjà retourné.');

        $result = DB::transaction(function () use ($loan) {
            $lockedLoan = Loan::query()->with('book')->lockForUpdate()->findOrFail($loan->id);
            abort_if($lockedLoan->returned_at || $lockedLoan->status === 'returned', 422, 'Cet emprunt est déjà retourné.');

            if ($lockedLoan->fee_status === 'paid') {
                return ['loan' => $lockedLoan, 'order' => null, 'already_paid' => true];
            }

            $feeAmount = $lockedLoan->fee_amount ?: Loan::borrowingFeeFor($lockedLoan->book);
            $currency = $lockedLoan->fee_currency ?: config('payments.currency', 'XOF');

            if ($feeAmount <= 0) {
                $lockedLoan->update([
                    'fee_amount' => 0,
                    'fee_currency' => $currency,
                    'fee_status' => 'paid',
                    'fee_paid_at' => now(),
                    'access_expires_at' => now()->addDays(Loan::READING_ACCESS_DAYS),
                    'fee_provider' => 'free',
                    'fee_payment_reference' => 'BORROW-FEE-FREE-'.$lockedLoan->id,
                ]);

                return ['loan' => $lockedLoan, 'order' => null, 'already_paid' => true];
            }

            $order = Order::query()
                ->where('purpose', 'loan_borrowing_fee')
                ->where('loan_id', $lockedLoan->id)
                ->whereIn('status', ['pending', 'awaiting_payment'])
                ->lockForUpdate()
                ->first();

            if (! $order) {
                $order = Order::create([
                    'user_id' => $lockedLoan->user_id,
                    'loan_id' => $lockedLoan->id,
                    'purpose' => 'loan_borrowing_fee',
                    'reference' => 'EMP-'.strtoupper(Str::random(14)),
                    'subtotal' => $feeAmount,
                    'discount' => 0,
                    'total' => $feeAmount,
                    'currency' => $currency,
                    'status' => 'pending',
                ]);
            }

            $lockedLoan->update([
                'fee_amount' => $feeAmount,
                'fee_currency' => $currency,
                'fee_status' => 'pending',
            ]);

            return ['loan' => $lockedLoan, 'order' => $order, 'already_paid' => false];
        });

        $audit->record($request, $result['order'] ? 'loan.borrowing_fee_payment_requested' : 'loan.borrowing_fee_paid', $result['loan'], [
            'book_id' => $result['loan']->book_id,
            'fee_amount' => $result['loan']->fee_amount,
            'order_id' => $result['order']?->id,
        ]);

        return response()->json([
            'loan' => $result['loan']->fresh()->load($this->loanRelations($request->user())),
            'order' => $result['order'],
            'payment_url' => $result['order'] ? route('orders.show', $result['order']) : null,
            'already_paid' => $result['already_paid'],
        ]);
    }

    public function markReturned(Request $request, Loan $loan, AuditLogger $audit)
    {
        $returned = DB::transaction(function () use ($request, $loan) {
            $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            abort_unless($request->user()->isAdmin() || $lockedLoan->user_id === $request->user()->id, 403);
            abort_if($lockedLoan->returned_at, 422, 'Cet emprunt a déjà été retourné.');
            $book = Book::query()->lockForUpdate()->findOrFail($lockedLoan->book_id);
            $chargedAmount = $lockedLoan->fee_status === 'paid' ? $lockedLoan->chargedAmountForReturn() : 0;
            $refundAmount = $lockedLoan->fee_status === 'paid' ? max(0, $lockedLoan->fee_amount - $chargedAmount) : 0;
            $refundStatus = match (true) {
                $refundAmount <= 0 => 'none',
                $lockedLoan->fee_provider === 'fake' => 'refunded',
                default => 'pending',
            };

            $lockedLoan->update([
                'returned_at' => now(),
                'returned_by' => $request->user()->id,
                'status' => 'returned',
                'fee_charged_amount' => $chargedAmount,
                'fee_refund_amount' => $refundAmount,
                'fee_refund_status' => $refundStatus,
                'fee_refunded_at' => $refundStatus === 'refunded' ? now() : null,
            ]);
            $book->increment('available_copies');
            Reservation::query()->where('book_id', $book->id)->where('status', 'waiting')->oldest('reserved_at')->first()?->update([
                'status' => 'ready',
                'expires_at' => now()->addDays(3),
            ]);

            return $lockedLoan;
        });
        $audit->record($request, 'loan.returned', $returned, [
            'book_id' => $returned->book_id,
            'fee_charged_amount' => $returned->fee_charged_amount,
            'fee_refund_amount' => $returned->fee_refund_amount,
            'fee_refund_status' => $returned->fee_refund_status,
        ]);

        return response()->json(['loan' => $returned->fresh()->load(['book.author', 'user:id,name,email'])]);
    }

    public function destroy(Request $request, Loan $loan, AuditLogger $audit)
    {
        abort_unless($loan->user_id === $request->user()->id, 403);
        abort_unless($loan->returned_at || $loan->status === 'returned', 422, 'Seuls les emprunts retournés peuvent être retirés de votre liste.');

        $loan->update(['hidden_by_user_at' => now()]);
        $audit->record($request, 'loan.hidden_by_member', $loan, ['book_id' => $loan->book_id]);

        return response()->noContent();
    }

    private function createLoan(User $member, int $bookId, int $lenderId, int $dueDays = Loan::READING_ACCESS_DAYS, ?int $reservationId = null): Loan
    {
        return DB::transaction(function () use ($member, $bookId, $lenderId, $dueDays, $reservationId) {
            $book = Book::query()->lockForUpdate()->findOrFail($bookId);
            abort_unless($book->is_active && $book->available_copies > 0, 422, 'Ce livre n’est pas disponible.');
            abort_if(Loan::query()->where('user_id', $member->id)->where('book_id', $book->id)->whereIn('status', ['borrowed', 'overdue'])->exists(), 422, 'Ce lecteur a déjà emprunté ce livre.');

            if ($reservationId) {
                $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservationId);
                abort_unless($reservation->user_id === $member->id && $reservation->book_id === $book->id && in_array($reservation->status, ['waiting', 'ready'], true), 422, 'La réservation sélectionnée ne correspond pas à cet emprunt.');
                $reservation->update(['status' => 'fulfilled', 'fulfilled_at' => now()]);
            }

            $book->decrement('available_copies');
            $feeAmount = Loan::borrowingFeeFor($book);

            return Loan::create([
                'user_id' => $member->id,
                'book_id' => $book->id,
                'loaned_by' => $lenderId,
                'borrowed_at' => now(),
                'due_at' => now()->addDays($dueDays),
                'status' => 'borrowed',
                'fee_amount' => $feeAmount,
                'fee_currency' => config('payments.currency', 'XOF'),
                'fee_status' => $feeAmount > 0 ? 'pending' : 'paid',
                'fee_paid_at' => $feeAmount > 0 ? null : now(),
                'access_expires_at' => $feeAmount > 0 ? null : now()->addDays(Loan::READING_ACCESS_DAYS),
            ]);
        });
    }

    private function loanRelations(User $user): array
    {
        return [
            'book.author',
            'book.category',
            'user:id,name,email',
            'book.readingDocuments' => fn ($query) => $query
                ->when(! $user->isAdmin(), fn ($documents) => $documents->where('is_active', true))
                ->latest(),
        ];
    }
}
