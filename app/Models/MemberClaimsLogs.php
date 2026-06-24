<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberClaimsLogs extends Model
{
    use HasFactory;

    protected $fillable = ['claim_id', 'from', 'status', 'log'];

    public function claim () {
        return $this->belongsTo(MemberClaims::class);
    }
}
