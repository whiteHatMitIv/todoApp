<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Task;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TaskReminder;
use Carbon\Carbon;

class SendTaskRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_tasks_send_reminders_sends_notification_and_updates_tasks()
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'user@example.test']);

        // Create a task whose target_date is within the ±5 minute window
        $task = Task::create([
            'user_id' => $user->id,
            'task' => 'Reminder test',
            'target_date' => Carbon::now()->addMinutes(3),
            'state' => 'pending',
            'reminder_sent' => false,
        ]);

        $this->artisan('tasks:send-reminders')->assertExitCode(0);

        Notification::assertSentTo($user, TaskReminder::class);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'reminder_sent' => true,
        ]);
    }
}
