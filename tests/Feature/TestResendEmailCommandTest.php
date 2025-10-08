<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Task;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Mail;
use App\Notifications\TaskReminder;
use Illuminate\Support\Facades\Artisan;

class TestResendEmailCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_task_option_sends_mail_and_notification()
    {
        Mail::fake();
        Notification::fake();

        $user = User::factory()->create(['email' => 'testuser@example.test']);

        $this->artisan('test:resend', ['--user' => $user->id, '--create-task' => true])
             ->assertExitCode(0)
             ->expectsOutputToContain('Tâche de test créée');

        // A notification should have been queued/sent
        Notification::assertSentTo($user, TaskReminder::class);

        // A task should exist for the user
        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
        ]);
    }

    public function test_task_option_sends_mail_and_notification()
    {
        Mail::fake();
        Notification::fake();

        $user = User::factory()->create(['email' => 'another@example.test']);

        $task = Task::create([
            'user_id' => $user->id,
            'task' => 'Existing task',
            'state' => 'pending',
        ]);

        $this->artisan('test:resend', ['--user' => $user->id, '--task' => $task->id])
             ->assertExitCode(0)
             ->expectsOutputToContain("Envoi d'email de test");

        Notification::assertSentTo($user, TaskReminder::class);
    }
}
