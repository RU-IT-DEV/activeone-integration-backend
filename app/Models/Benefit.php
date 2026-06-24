<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Benefit extends Model
{
    use HasFactory;
    protected $fillable = [
        'code','company_id','type','name','description', 'uflex'
    ];

    // Relationship to BenefitPeriods
    public function periods()
    {
        return $this->hasMany(BenefitPeriod::class);
    }

    public function currentPeriod()
    {
        return $this->hasOne(BenefitPeriod::class)->where('status', 'active')->where('is_current', 1);
    }

    // Relationship to BenefitCategories
    public function categories()
    {
        return $this->hasMany(BenefitCategories::class);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function planLink()
    {
        return $this->hasManyThrough(MemberPlanLink::class, BenefitPeriod::class);
    }

    public function categoryOptions()
    {
        return $this->hasMany(BenefitCategoryOptions::class);
    }

    public function memberPlanLinks()
    {
        return $this->hasManyThrough(MemberPlanLink::class, BenefitPeriod::class)
            ->where('benefit_periods.is_current', true);
    }

}
