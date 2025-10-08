<?php

namespace Tests\Feature\Queue;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Models\User;
use App\Models\Task;
use App\Notifications\TaskReminder;
use Carbon\Carbon;

class NotificationQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_reminder_notification_is_dispatched()
    {
        Notification::fake();

        $user = User::factory()->create();

        $task = Task::create([
            'user_id' => $user->id,
            'task' => 'Queue test',
            'target_date' => Carbon::now()->addMinutes(1),
            'state' => 'pending',
            'reminder_sent' => false,
        ]);

        // Trigger the sending logic
        $this->artisan('tasks:send-reminders')->assertExitCode(0);

        Notification::assertSentTo($user, TaskReminder::class);
    }
}
