<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberClaimsAttachments extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_claim_id','filepath',
    ];

    public function memberClaim()
    {
        return $this->belongsTo(MemberClaims::class, 'member_claim_id');
    }
}
