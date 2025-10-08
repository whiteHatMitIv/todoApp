<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Task;
use Carbon\Carbon;

class TaskReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Task $task) {}

    public function via(object $notifiable): array
    {
        if (!$this->task->exists) {
            return [];
        }
        
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable)
    {
        if (!$this->task->exists) {
            return null;
        }

        if (!$this->task->target_date) {
            $dateMessage = "Aucune date de rappel définie";
        } else {
            $targetDate = $this->task->target_date instanceof Carbon 
                ? $this->task->target_date 
                : Carbon::parse($this->task->target_date);
            
            $dateMessage = $targetDate->format('d/m/Y à H:i');
        }

        return (new MailMessage)
                    ->subject("🔔 Rappel de tâche - {$this->task->task}")
                    ->greeting("Bonjour {$notifiable->name},")
                    ->line("Vous avez un rappel pour la tâche suivante :")
                    ->line("**{$this->task->task}**")
                    ->line("Date de rappel : {$dateMessage}")
                    ->action('Voir mes tâches', url('/tasks'))
                    ->line('Merci d\'utiliser notre application !');
    }

    public function toArray(object $notifiable): array
    {
        $dateMessage = "Aucune date définie";
        
        if ($this->task->target_date) {
            $targetDate = $this->task->target_date instanceof Carbon 
                ? $this->task->target_date 
                : Carbon::parse($this->task->target_date);
            
            $dateMessage = $targetDate->format('d/m/Y à H:i');
        }

        return [
            'task_id' => $this->task->id,
            'task' => $this->task->task,
            'target_date' => $this->task->target_date,
            'formatted_date' => $dateMessage,
            'message' => "Rappel : {$this->task->task} - prévu pour {$dateMessage}",
            'type' => 'task_reminder'
        ];
    }
}