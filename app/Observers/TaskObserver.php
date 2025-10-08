<?php

namespace App\Observers;

use App\Models\Task;
use Illuminate\Support\Facades\DB;

class TaskObserver
{
    public function deleting(Task $task)
    {
        DB::table('jobs')
            ->where('payload', 'like', '%"task_id":' . $task->id . '%')
            ->orWhere('payload', 'like', '%TaskReminder%')
            ->where('payload', 'like', '%' . $task->id . '%')
            ->delete();
    }
}