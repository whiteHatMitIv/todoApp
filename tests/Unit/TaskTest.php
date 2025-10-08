<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_attributes_and_casts()
    {
        $user = User::factory()->create();

        $task = Task::create([
            'user_id' => $user->id,
            'task' => 'Tâche de test',
        ]);

        $this->assertEquals('pending', $task->state);
        $this->assertFalse($task->reminder_sent);
        $this->assertTrue(isset($task->target_date) === false || $task->target_date === null);
        $this->assertArrayHasKey('target_date', $task->getCasts());
    }
}
