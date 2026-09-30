<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderLog extends Model
{
    protected $fillable = [
        'table',
        'auditable_id',
        'auditable_by',
        'action',
        'summary',
        'status',
        'value'
    ];

    public $casts = [
        'value' => 'array'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'id', 'auditable_id')->where('table', 'orders');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id', 'auditable_by');
    }
}
