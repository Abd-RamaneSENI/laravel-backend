<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AuthController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:255', 'unique:users'], 'password' => ['required', 'confirmed', Password::min(12)]]);
        $user = User::create($data);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('application');
    }

    public function login()
    {
        return view('auth.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Identifiants invalides.'])->onlyInput('email');
        } $request->session()->regenerate();

        return redirect()->intended(route('application'));
    }

    public function forgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        try {
            $status = PasswordBroker::sendResetLink($data);
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withInput($request->only('email'))
                ->with('warning', $this->passwordResetMailErrorMessage($e));
        }

        return $status === PasswordBroker::RESET_THROTTLED
            ? back()->withErrors(['email' => 'Veuillez patienter avant de demander un nouveau lien de réinitialisation.'])
            : back()->with('success', 'Un lien de réinitialisation a été envoyé si cette adresse existe.');
    }

    public function resetPasswordForm(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $status = PasswordBroker::reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
        });

        return $status === PasswordBroker::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Mot de passe réinitialisé. Vous pouvez vous connecter.')
            : back()->withErrors(['email' => 'Le lien de réinitialisation est invalide ou expiré.'])->onlyInput('email');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function passwordResetMailErrorMessage(Throwable $e): string
    {
        $message = $e->getMessage();

        if (Str::contains($message, ['socket', 'smtp.gmail.com', '10013'])) {
            return "La connexion SMTP est bloquée par Windows. Autorisez PHP/Apache à sortir vers smtp.gmail.com sur le port 587, puis redemandez le lien de réinitialisation.";
        }

        return "Le lien de réinitialisation n'a pas pu être envoyé pour le moment. Vérifiez SMTP puis réessayez.";
    }
}
