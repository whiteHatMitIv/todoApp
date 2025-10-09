<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\PersonalAccessToken;

class TokenValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_token_returns_ok()
    {
        $user = User::factory()->create();

        $token = $user->createToken('test-token', ['*'])->plainTextToken;

        $response = $this->postJson('/api/token/validate', [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200)
            ->assertJson(['valid' => true])
            ->assertJsonStructure(['user' => ['id', 'email', 'name'], 'abilities', 'expires_at']);
    }

    public function test_expired_token_returns_expired()
    {
        $user = User::factory()->create();

        $tokenInstance = $user->tokens()->create([
            'name' => 'expired',
            'token' => hash('sha256', 'plain-expired-token'),
            'abilities' => ['*'],
            'expires_at' => now()->subHour(),
        ]);

        $plain = $tokenInstance->id . '|plain-expired-token';

        $response = $this->postJson('/api/token/validate', ['token' => $plain]);

        $response->assertStatus(200)
            ->assertJson(['valid' => false, 'reason' => 'expired']);
    }
}
