<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BenefitPeriod extends Model
{
    use HasFactory;
    protected $fillable = ['benefit_id', 'status', 'effectivity_date', 'expiration_date', 'is_current'];

    // Relationship to Benefit
    public function benefit()
    {
        return $this->belongsTo(Benefit::class);
    }

    public function members()
    {
        return $this->hasManyThrough(Members::class, MemberPlanLink::class, 'benefit_period_id', 'id', 'id', 'member_id');
    }

    public function planLinks()
    {
        return $this->hasMany(MemberPlanLink::class);
    }
}
