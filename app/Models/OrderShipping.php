<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderShipping extends Model
{
    protected $fillable = [
        'order_id', 'address1', 'address2',
        'city', 'countryCode', 'provinceCode',
        'zip', 'firstName', 'lastName', 'phone'
    ];
    
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
