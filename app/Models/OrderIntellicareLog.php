<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderIntellicareLog extends Model
{
    protected $fillable = [
        'order_id',
        'account_no',
        'contract',
        'branch',
        'receipt_number',
        'prccode',
        'diagnosis',
        'prescription_location'
    ];

    protected $casts = [
        'diagnosis' => 'array'
    ];

    public function medicines()
    {
        return $this->hasManyThrough(OrderDetails::class, Order::class, 'id', 'order_id');
    }
}
