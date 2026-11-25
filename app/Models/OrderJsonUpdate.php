<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderJsonUpdate extends Model
{
    protected $fillable = [
        'update_type',
        'started_at',
        'processing_at',
        'completed_at',
        'status',
    ];
}
