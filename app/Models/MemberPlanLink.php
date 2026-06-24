<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberPlanLink extends Model
{
    use HasFactory;
    protected $fillable = [
        'member_id',
        'benefit_period_id',
        'enrollment_date',
        'valid_until',
        'status',
    ];

    // Relationship to Members
    public function member()
    {
        return $this->belongsTo(Members::class);
    }

    public function benefit()
    {
        return $this->hasOneThrough(Benefit::class, BenefitPeriod::class, 'id', 'id', 'benefit_period_id', 'benefit_id');
    }

    public function activeBenefit()
    {
        return $this->benefit()
            ->where('benefit_periods.status', 'active')
            ->where('benefit_periods.is_current', true);
    }

    public function benefitPeriod()
    {
        return $this->belongsTo(BenefitPeriod::class);
    }

    public function selectablePeriodsForAdj()
    {
        return $this->hasOne(BenefitPeriod::class, 'id', 'benefit_period_id')->where('adj_selectable_flg', 1);
    }

    public function planBuckets()
    {
        return $this->hasMany(MemberPlanBucket::class);
    }

    public function planActiveBuckets()
    {
        return $this->hasMany(MemberPlanBucket::class)->where('remaining_limit', '>', 0.00);
    }

    public function memberClaims()
    {
        return $this->hasMany(MemberClaims::class, 'member_plan_links_id', 'id');
    }
    
}
