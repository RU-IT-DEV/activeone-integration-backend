<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderDetails extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id', 'shopify_productId', 'shopify_product_price', 
        'image_url', 'quantity', 'sku', 'icd', 'icdcode',
        'code', 'title', 'type', 'variantTitle', 'unit',
        'amount', 'vat_amount', 'no_vat_amount', 'taxable',
        'is_prescribed', 'reason'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
