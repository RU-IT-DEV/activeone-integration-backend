<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'customer_id', 'customer_email', 'customer_name', 
        'shopify_cart_id', 'shopify_order_name', 'shopify_order_id',
        'order_url', 'financialStatus', 'totalAmount',
        'test', 'intellicare_status', 'shopify_status',
        'activeone_status'
    ];
    
    public function lineItems()
    {
        return $this->hasMany(OrderDetails::class, 'order_id');
    }

    public function shippingAddress()
    {
        return $this->hasOne(OrderShipping::class, 'order_id');
    }

    public function billingAddress()
    {
        return $this->hasOne(OrderBilling::class, 'order_id');
    }

    public function intellicareLog()
    {
        return $this->hasOne(OrderIntellicareLog::class, 'order_id');
    }

    public function prescriptions()
    {
        return $this->hasMany(OrderPrescription::class, 'order_id');
    }
}
