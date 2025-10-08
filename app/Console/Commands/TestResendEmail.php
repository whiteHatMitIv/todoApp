<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Task;
use Illuminate\Support\Facades\Mail;

class TestResendEmail extends Command
{
    protected $signature = 'test:resend 
                            {--user=1 : ID de l\'utilisateur}
                            {--task= : ID de la tâche existante}
                            {--create-task : Créer une nouvelle tâche de test}';
    
    protected $description = 'Tester l\'envoi d\'email via Resend';

    public function handle()
    {
        $user = User::find($this->option('user'));
        
        if (!$user) {
            $this->error('Utilisateur non trouvé');
            return 1;
        }

        if ($this->option('task')) {
            $task = Task::find($this->option('task'));
        } elseif ($this->option('create-task')) {
            $task = Task::create([
                'user_id' => $user->id,
                'task' => 'Tâche de test pour vérifier l\'envoi d\'email',
                'target_date' => now()->addMinutes(5),
                'state' => 'pending',
                'reminder_sent' => false,
            ]);
            $this->info('Tâche de test créée avec ID: ' . $task->id);
        } else {
            $this->error('Spécifiez --task=ID ou --create-task');
            return 1;
        }

        if (!$task) {
            $this->error('Tâche non trouvée');
            return 1;
        }

        $this->info("Envoi d'email de test à: " . $user->email);
        
        try {
            Mail::raw('Test Resend - Si vous recevez ceci, Resend fonctionne!', function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('Test Resend - Laravel Task App');
            });
            
            $this->info('✅ Email de test envoyé avec Mail::raw');
            
            $user->notify(new \App\Notifications\TaskReminder($task));
            $this->info('✅ Notification TaskReminder envoyée');
            
        } catch (\Exception $e) {
            $this->error('❌ Erreur: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}