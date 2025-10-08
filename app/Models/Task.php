<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'task', 'state', 'target_date', 'reminder_sent'];

    protected $attributes = [
        'state' => 'pending',
        'reminder_sent' => false,
    ];

    protected $casts = [
        'target_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
