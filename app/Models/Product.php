<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'sku',
        'netsuite_item_id',
        'name',
        'description',
        'amount',
        'vat_percentage',
        'no_vat_amount',
        'vatable',
        'currency',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'vat_percentage' => 'decimal:4',
        'no_vat_amount' => 'decimal:2',
        'vatable' => 'boolean',
    ];
}
