<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerEmailVerification extends Model
{
    protected $table = 'customer_email_verifications';
    protected $fillable = [
        'email', 'token', 'expires_at'
    ];
}
