<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddTask;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $userId = Auth::id();
        $tasks = Task::where('user_id', $userId)
                    ->orderBy('created_at', 'desc')
                    ->paginate(10);

        return response()->json($tasks);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AddTask $request)
    {
        $userId = Auth::id();

        $validated = $request->validated();

        $task = Task::create([
            'user_id' => $userId,
            'task' => $validated['task'],
            'target_date' => $validated['target_date'] ?? null,
            'reminder_sent' => false,
        ]);

        return response()->json($task, 201);
    }



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Task $task)
    {
        Gate::authorize('update', $task);

        $task->state = $task->state === 'pending' ? 'done' : 'pending';
        $task->save();

        return response()->json([
            'message' => 'Statut de la tâche mis à jour avec succès',
            'task' => $task
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        Gate::authorize('delete', $task);
        $task->delete();

        return response()->json(null, 204);
    }
}
