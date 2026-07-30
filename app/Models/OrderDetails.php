<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDetails extends Model
{
    protected $fillable = [
        'order_id', 'shopify_productId', 'shopify_product_price', 
        'image_url', 'quantity', 'sku',
        'code', 'title', 'type', 'variantTitle', 'unit',
        'amount', 'vat_amount', 'no_vat_amount', 'taxable',
        'is_prescribed'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
