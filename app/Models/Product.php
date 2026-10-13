<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'sku', 'name', 'amount', 'no_vat_amount', 'vatable',
        'netsuite_item_id',
        'currency',
        'description',
        'status',
    ];
}
