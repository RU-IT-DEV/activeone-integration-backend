<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaimQrScanLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_claim_id',
        'claim_id',
        'email',
        'employee_name',
        'box_no',
        'is_email_sent',
        'scanned_at',
        'actual_received_date',
        'remarks',
    ];

    public function memberClaim()
    {    
        return $this->belongsTo(MemberClaims::class, 'member_claim_id', 'id');
    }
}

    