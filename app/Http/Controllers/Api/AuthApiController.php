<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'birth_date' => ['required', 'date', 'before:today'],
            'phone' => ['required', 'string', 'max:32'],
            'country' => ['required', 'string', 'max:100'],
            'department' => ['nullable', 'required_if:country,Bénin', 'string', 'max:100'],
            'commune' => ['nullable', 'required_if:country,Bénin', 'string', 'max:100'],
            'arrondissement' => ['nullable', 'required_if:country,Bénin', 'string', 'max:100'],
            'profession' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', $this->passwordRule()],
        ]);

        $data['name'] = trim($data['first_name'].' '.$data['last_name']);
        $user = new User($data);
        if (strcasecmp((string) $user->email, (string) env('SUPER_ADMIN_EMAIL')) === 0) {
            $user->role = 'super_admin';
        }
        $user->save();
        $user->sendEmailVerificationNotification();
        $audit->record($request, 'account.registered', $user);

        return response()->json(['message' => 'Votre inscription est enregistrée. Consultez votre e-mail pour valider le compte.'], 201);
    }

    public function login(Request $request, AuditLogger $audit)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password'], 'is_active' => true], $request->boolean('remember'))) {
            return response()->json(['message' => 'Identifiants invalides ou compte désactivé.'], 422);
        }

        if (! $request->user()->hasVerifiedEmail()) {
            Auth::logout();
            return response()->json(['message' => 'Validez votre adresse e-mail avant de vous connecter.'], 403);
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

    public function resendVerification(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::query()->where('email', $data['email'])->first();

        if ($user && ! $user->hasVerifiedEmail() && $user->is_active) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'Si ce compte existe et reste à valider, un nouveau lien a été envoyé.']);
    }

    public function changePassword(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', $this->passwordRule()],
        ]);

        $request->user()->forceFill(['password' => Hash::make($data['password'])])->save();
        $audit->record($request, 'account.password_changed', $request->user());

        return response()->json(['message' => 'Votre mot de passe a été modifié.']);
    }

    private function user(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'is_admin' => $user->isAdmin(), 'is_super_admin' => $user->role === 'super_admin'];
    }

    private function passwordRule(): Password
    {
        return Password::min(8)->letters()->mixedCase()->numbers()->symbols();
    }
}
