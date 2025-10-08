<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_to_google()
    {
        $response = $this->getJson('/api/auth/google');
        // Socialite performs a redirect to provider
        $response->assertStatus(302);
    }


    public function test_google_callback_creates_new_user()
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google123');
        $socialiteUser->shouldReceive('getEmail')->andReturn('google@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Google User');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('avatar.jpg');
        $socialiteUser->shouldReceive('getToken')->andReturn('token123');
        $socialiteUser->shouldReceive('getRefreshToken')->andReturn('refresh123');
        $socialiteUser->shouldReceive('getExpiresIn')->andReturn(3600);

        Socialite::shouldReceive('driver->stateless->user')
            ->andReturn($socialiteUser);

        $response = $this->getJson('/api/auth/google/callback');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'auth_type']
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'google@example.com',
            'name' => 'Google User'
        ]);

        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'google',
            'provider_id' => 'google123'
        ]);
    }

    public function test_cannot_register_social_with_existing_email()
    {
        // Crée un utilisateur standard existant
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google123');
        $socialiteUser->shouldReceive('getEmail')->andReturn('existing@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Google User');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('avatar.jpg');

        Socialite::shouldReceive('driver->stateless->user')
            ->andReturn($socialiteUser);

        $response = $this->getJson('/api/auth/google/callback');

        $response->assertStatus(422)
            ->assertJson(['error' => 'Un compte existe déjà avec cette adresse email.']);
    }

    public function test_cannot_login_standard_if_social_account()
    {
        $user = User::factory()->create([
            'email' => 'social@example.com',
            'password' => Hash::make('password'),
        ]);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'google123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'social@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}