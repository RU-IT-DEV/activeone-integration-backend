<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyCheckoutSession extends Model
{
    protected $table = 'checkout_sessions';

    protected $fillable = [
        'token',
        'shop',
        'shopify_customer_id',
        'cart_token',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];
}
