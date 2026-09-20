<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Order;
use App\Models\ReadingDocument;
use App\Models\Resource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    private const MANAGEABLE_ROLES = ['student', 'vendor', 'admin'];

    public function index()
    {
        return response()->json([
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role', 'is_active', 'created_at']),
        ]);
    }

    public function updateRole(Request $request, User $user, AuditLogger $audit)
    {
        abort_if($request->user()->is($user), 422, 'Vous ne pouvez pas modifier votre propre rôle.');

        $data = $request->validate([
            'role' => ['required', 'string', Rule::in(self::MANAGEABLE_ROLES)],
        ]);

        if ($user->isAdmin() && $data['role'] !== 'admin' && User::query()->whereIn('role', ['admin', 'super_admin'])->count() <= 1) {
            abort(422, 'Au moins un administrateur doit rester actif.');
        }

        $previousRole = $user->role;
        $user->forceFill(['role' => $data['role']])->save();
        $audit->record($request, 'user.role_updated', $user, ['previous_role' => $previousRole, 'new_role' => $user->role]);

        return response()->json(['user' => $this->userData($user)]);
    }

    public function destroy(Request $request, User $user, AuditLogger $audit)
    {
        abort_if($request->user()->is($user), 422, 'Vous ne pouvez pas supprimer votre propre compte.');

        if ($user->isAdmin() && User::query()->whereIn('role', ['admin', 'super_admin'])->count() <= 1) {
            abort(422, 'Le dernier administrateur ne peut pas être supprimé.');
        }

        if ($this->hasProtectedHistory($user)) {
            abort(422, 'Ce compte possède des commandes, prêts ou contenus. Conservez-le pour préserver l’historique.');
        }

        DB::transaction(function () use ($request, $user, $audit) {
            $audit->record($request, 'user.deleted', $user, ['deleted_user_email' => $user->email]);
            $user->delete();
        });

        return response()->noContent();
    }

    private function hasProtectedHistory(User $user): bool
    {
        return Resource::query()->where('author_id', $user->id)->exists()
            || ReadingDocument::query()->where('uploaded_by', $user->id)->exists()
            || Loan::query()->where('user_id', $user->id)->exists()
            || Order::query()->where('user_id', $user->id)->exists();
    }

    private function userData(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'role', 'is_active', 'created_at']);
    }
}
