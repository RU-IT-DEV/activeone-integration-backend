<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchedulerLog extends Model
{
    use HasFactory;
    
    protected $fillable = ['job_name', 'status', 'message', 'run_time'];
}
