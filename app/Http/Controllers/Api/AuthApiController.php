<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthApiController extends Controller
{
    public function me(Request $request)
    {
        return response()->json(['user' => $request->user() ? $this->user($request->user()) : null]);
    }

    public function register(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $user = User::create($data);
        Auth::login($user);
        $request->session()->regenerate();
        $audit->record($request, 'account.registered', $user);

        return response()->json(['user' => $this->user($user)], 201);
    }

    public function login(Request $request, AuditLogger $audit)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password'], 'is_active' => true], $request->boolean('remember'))) {
            return response()->json(['message' => 'Identifiants invalides ou compte désactivé.'], 422);
        }

        $request->session()->regenerate();
        $audit->record($request, 'account.logged_in', $request->user());

        return response()->json(['user' => $this->user($request->user())]);
    }

    public function logout(Request $request, AuditLogger $audit)
    {
        $audit->record($request, 'account.logged_out', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    private function user(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'is_admin' => $user->isAdmin()];
    }
}
