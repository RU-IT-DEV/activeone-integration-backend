<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderLog extends Model
{
    protected $fillable = [
        'table',
        'auditable_id',
        'action',
        'summary',
        'status',
        'value'
    ];

    public $casts = [
        'value' => 'array'
    ];
}
