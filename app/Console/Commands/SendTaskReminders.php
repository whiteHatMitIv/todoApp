<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Task;
use App\Notifications\TaskReminder;
use Exception;
use Illuminate\Support\Facades\Log;
use Resend\Exceptions\ErrorException;

class SendTaskReminders extends Command
{
    protected $signature = 'tasks:send-reminders';
    protected $description = 'Envoyer les rappels de tâches via Resend';

    public function handle()
    {        
        $tasks = Task::with('user')
                    ->whereBetween('target_date', [now()->subMinutes(5), now()->addMinutes(5)])
                    ->where('state', 'pending')
                    ->where('reminder_sent', false)
                    ->whereNull('deleted_at')
                    ->get();

        $sentCount = 0;
        $errorCount = 0;

        foreach ($tasks as $task) {
            try {
                $user = $task->user;

                if ($user && $task->exists && $user->email) {
                    $user->notify(new TaskReminder($task));
                    $task->update(['reminder_sent' => true]);
                    $this->info("✅ Rappel envoyé pour: {$task->task} à {$user->email}");
                    $sentCount++;
                } else {
                    $this->warn("⚠️ Tâche, utilisateur ou email manquant - Tâche ID: {$task->id}");
                }
            } catch (ErrorException $e) {
                $this->error("❌ Erreur Resend pour la tâche {$task->id}: " . $e->getMessage());
                Log::error('Resend error', ['task_id' => $task->id, 'error' => $e->getMessage()]);
                $errorCount++;
            } catch (Exception $e) {
                $this->error("❌ Erreur générale pour la tâche {$task->id}: " . $e->getMessage());
                Log::error('Notification error', ['task_id' => $task->id, 'error' => $e->getMessage()]);
                $errorCount++;
            }
        }

        $this->info("Traitement terminé : {$sentCount} rappels envoyés, {$errorCount} erreurs.");
        
        return Command::SUCCESS;
    }
}