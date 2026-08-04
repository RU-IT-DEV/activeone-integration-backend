<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPrescription extends Model
{
    protected $fillable = [
        'reference_number',
        'account_number',
        'file_path',
        'file_name',
        'location',
    ];

    public function order ()
    {
        return $this->belongsTo(Order::class);
    }
}
