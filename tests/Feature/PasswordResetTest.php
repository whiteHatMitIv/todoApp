<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;
use App\Notifications\ResetPasswordNotification as ResetPassword;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_password_reset()
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Lien de réinitialisation envoyé']);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_cannot_request_reset_for_social_account()
    {
        $user = User::factory()->create([
            'email' => 'social@example.com',
        ]);

        \App\Models\SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'google123',
        ]);

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'social@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Cet email est associé à un compte google. Veuillez utiliser la connexion sociale.']);
    }

    public function test_user_can_reset_password()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $token = \Illuminate\Support\Facades\Password::createToken($user);

        $response = $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Mot de passe réinitialisé avec succès']);

        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));
    }
}