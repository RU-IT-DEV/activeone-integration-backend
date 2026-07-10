<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyIntegrationAuth extends Model
{
    protected $fillable = [
        'shop_client_id',
        'access_token',
        'expires_at',
    ];
}
