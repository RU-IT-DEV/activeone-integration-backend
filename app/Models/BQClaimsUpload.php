<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BQClaimsUpload extends Model
{
    use HasFactory;

    protected $table = 'bq_claims_upload';

    protected $casts = [
        'data' => 'array',
    ];

    protected $fillable = [
        'user_id', 'member_claim_id', 'data', 'is_pushed'
    ];
}
