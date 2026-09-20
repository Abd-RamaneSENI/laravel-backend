<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use RuntimeException;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_and_admin_can_open_password_recovery_page(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Récupérer le mot de passe');
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'admin@example.com',
            'password' => 'nouveau-password-2026',
            'password_confirmation' => 'nouveau-password-2026',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('nouveau-password-2026', $user->fresh()->password));
    }

    public function test_password_reset_link_is_sent_by_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'etudiant@example.com']);

        $this->post(route('password.email'), ['email' => 'etudiant@example.com'])
            ->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_shows_clear_message_when_smtp_is_blocked(): void
    {
        User::factory()->create(['email' => 'etudiant@example.com']);

        ResetPassword::toMailUsing(function (): never {
            throw new RuntimeException('Connection could not be established with host "smtp.gmail.com:587": socket 10013.');
        });

        try {
            $this->from(route('password.request'))
                ->post(route('password.email'), ['email' => 'etudiant@example.com'])
                ->assertRedirect(route('password.request'))
                ->assertSessionHas('warning');
        } finally {
            ResetPassword::$toMailCallback = null;
        }
    }
}
