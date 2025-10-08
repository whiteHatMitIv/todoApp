<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_token()
    {
        $payload = [
            'name' => 'Test',
            'email' => 'register@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(201)
                 ->assertJsonStructure(['token']);
    }

    public function test_login_and_logout_work()
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $login->assertStatus(200)->assertJsonStructure(['token']);

        $token = $login->json('token');

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
             ->postJson('/api/logout');

    $response->assertStatus(200)->assertJson(['message' => 'Déconnexion réussie']);
    }
}
