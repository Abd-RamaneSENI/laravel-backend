<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Reservation;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $query = Reservation::query()->with(['book.author', 'user:id,name,email'])->latest('reserved_at');
        if (! $request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request, Book $book, AuditLogger $audit)
    {
        abort_if($book->available_copies > 0, 422, 'Ce livre est disponible : empruntez-le directement.');
        abort_if(Reservation::query()->where('user_id', $request->user()->id)->where('book_id', $book->id)->where('status', 'waiting')->exists(), 422, 'Vous avez déjà une réservation active pour ce livre.');

        $reservation = Reservation::create(['user_id' => $request->user()->id, 'book_id' => $book->id, 'reserved_at' => now(), 'status' => 'waiting']);
        $audit->record($request, 'reservation.created', $reservation, ['book_id' => $book->id]);

        return response()->json(['reservation' => $reservation->load('book.author')], 201);
    }

    public function cancel(Request $request, Reservation $reservation, AuditLogger $audit)
    {
        abort_unless($request->user()->isAdmin() || $reservation->user_id === $request->user()->id, 403);
        abort_if($reservation->status !== 'waiting', 422, 'Cette réservation n’est plus active.');
        $reservation->update(['status' => 'cancelled']);
        $audit->record($request, 'reservation.cancelled', $reservation, ['book_id' => $reservation->book_id]);

        return response()->noContent();
    }

    public function updateStatus(Request $request, Reservation $reservation, AuditLogger $audit)
    {
        $data = $request->validate(['status' => ['required', 'in:waiting,ready,cancelled']]);
        abort_if(in_array($reservation->status, ['fulfilled', 'cancelled'], true), 422, 'Cette réservation est clôturée.');
        $reservation->update([
            'status' => $data['status'],
            'expires_at' => $data['status'] === 'ready' ? now()->addDays(3) : null,
        ]);
        $audit->record($request, 'reservation.status_updated', $reservation, ['status' => $data['status']]);

        return response()->json(['reservation' => $reservation->fresh()->load(['book.author', 'user:id,name,email'])]);
    }
}
