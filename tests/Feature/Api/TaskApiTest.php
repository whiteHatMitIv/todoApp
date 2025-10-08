<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Task;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    protected function authHeaderFor(User $user)
    {
        $token = $user->createToken('api-token')->plainTextToken;
        return ['Authorization' => 'Bearer ' . $token];
    }

    public function test_store_and_index_tasks()
    {
        $user = User::factory()->create();

        $payload = ['task' => 'API create task'];

        $response = $this->withHeaders($this->authHeaderFor($user))
                         ->postJson('/api/tasks', $payload);

        $response->assertStatus(201)->assertJsonFragment(['task' => 'API create task']);

        $index = $this->withHeaders($this->authHeaderFor($user))->getJson('/api/tasks');
        $index->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_update_toggles_state_and_destroy()
    {
        $user = User::factory()->create();

        $task = Task::create([
            'user_id' => $user->id,
            'task' => 'To toggle',
            'state' => 'pending',
        ]);

        $update = $this->withHeaders($this->authHeaderFor($user))
                       ->putJson('/api/tasks/'.$task->id);

        $update->assertStatus(200)->assertJsonPath('task.state', 'done');

        $destroy = $this->withHeaders($this->authHeaderFor($user))
                        ->deleteJson('/api/tasks/'.$task->id);

        $destroy->assertStatus(204);
    }
}
