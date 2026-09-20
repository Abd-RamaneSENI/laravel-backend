<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Loan;
use App\Models\ReadingPresence;
use App\Models\Reservation;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->user()->isAdmin()) {
            return response()->json([
                'kind' => 'admin',
                'metrics' => [
                    'books' => Book::where('is_active', true)->count(),
                    'available_copies' => Book::sum('available_copies'),
                    'active_loans' => Loan::whereIn('status', ['borrowed', 'overdue'])->count(),
                    'waiting_reservations' => Reservation::where('status', 'waiting')->count(),
                    'online_readers' => ReadingPresence::query()->online()->count(),
                ],
                'overdue' => Loan::with(['book', 'user:id,name,email'])->whereIn('status', ['borrowed', 'overdue'])->where('due_at', '<', now())->orderBy('due_at')->take(8)->get(),
                'reservations' => Reservation::with(['book.author', 'user:id,name,email'])->whereIn('status', ['waiting', 'ready'])->oldest('reserved_at')->take(12)->get(),
                'reading_presences' => ReadingPresence::query()->online()->with(['user:id,name,email', 'document:id,title'])->latest('last_seen_at')->get(),
            ]);
        }

        return response()->json([
            'kind' => 'member',
            'metrics' => [
                'active_loans' => Loan::where('user_id', $request->user()->id)->whereIn('status', ['borrowed', 'overdue'])->count(),
                'due_soon' => Loan::where('user_id', $request->user()->id)->whereIn('status', ['borrowed', 'overdue'])->whereBetween('due_at', [now(), now()->addDays(3)])->count(),
                'reservations' => Reservation::where('user_id', $request->user()->id)->where('status', 'waiting')->count(),
            ],
        ]);
    }
}
