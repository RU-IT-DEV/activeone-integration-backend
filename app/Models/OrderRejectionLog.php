<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderRejectionLog extends Model
{

    protected $fillable = [
        'order_log_id', 'reason', 'comment'
    ];
    
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
