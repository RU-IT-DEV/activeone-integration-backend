<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaimsResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_claim_id',
        'member_id',
        'member_plan_links_id',
        'approved_amount',
        'rejected_amount',
        'final_status',
        'member_plan_bucket_id',
        'adjudicated_by',
        'remarks',
        'rejection_reason'
    ];

    public function planLink()
    {
        return $this->belongsTo(MemberPlanLink::class, 'member_plan_links_id');
    }

    public function claim()
    {
        return $this->belongsTo(MemberClaims::class, 'member_claim_id', 'id');
    }

    public function bucket()
    {
        return $this->belongsTo(MemberPlanBucket::class, 'member_plan_bucket_id', 'id');
    }
    public function adjudicator()
    {
        return $this->belongsTo(User::class, 'adjudicated_by', 'id');
    }

    public function member()
    {
        return $this->belongsTo(Members::class, 'member_id', 'id');
    }
}
