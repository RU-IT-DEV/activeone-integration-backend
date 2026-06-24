<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;

class Members extends Authenticatable
{
    use HasFactory, HasApiTokens;
    use SoftDeletes;

    const PRIMARY_KEY = 'id';

    protected $fillable = [
        'flexicare_id',
        'company_code',
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'payee_code',
        'member_classification',
        'employee_no',
        'birthdate',
        'gender',
        'civil_status',
        'status',
        'email',
        'position',
        'date_hired',
        'division',
        'member_type',
        'principal_id',
        'date_endorsed',
        'salary_grade',
        'deactivation_date'
    ];

    // Relationship to MemberPlanLink
    public function planLink()
    {
        return $this->hasMany(MemberPlanLink::class, 'member_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_code', 'code');
    }
    
    public function email_verifications ()
    {
        return $this->hasMany(MemberEmailVerification::class, 'member_id');
    }

    public function selectablePeriodsForAdj()
    {
        return $this->hasManyThrough(BenefitPeriod::class, MemberPlanLink::class, 'member_id', 'id', 'id', 'benefit_period_id')
            ->where('adj_selectable_flg', 1);
    }

    public function activePlanLinks ()
    {
        return $this->hasMany(MemberPlanLink::class, 'member_id')
            ->where('status', 'active')
            ->whereHas('planActiveBuckets');
    }
    
    public function inactivePlanLinks ()
    {
        return $this->hasMany(MemberPlanLink::class, 'member_id')
            ->where('status', '!=', 'active')
            ->whereHas('planActiveBuckets');
    }

    public function claims()
    {
        return $this->hasMany(MemberClaims::class, 'member_id');
    }

    public function pending_claims()
    {
        return $this->hasMany(MemberClaims::class, 'member_id')->where('status', 'Pending');
    }

    public function employmentHistory()
    {
        return $this->hasMany(MemberEmploymentHistories::class, 'member_id');
    }
    
    public function monthlyClaimsUsage()
    {
        return $this->hasMany(MemberClaims::class, 'member_id')
            ->select(
                'member_plan_links_id',
                DB::raw('SUM(total_amount) as total_amount'),
                DB::raw("DATE_FORMAT(created_at, '%m') as month"),
                DB::raw("DATE_FORMAT(created_at, '%Y') as year")
            )
            ->whereYear('created_at', now()->year)
            ->groupBy('member_plan_links_id', 'month', 'year');
    }

    public function defaultBankDetail ()
    {
        return $this->hasOne(MemberBankDetail::class, 'member_id')->limit(1);
    }

    public function bankDetails()
    {
        return $this->hasMany(MemberBankDetail::class, 'member_id');
    }

    /**
     * Deactivate benefits from Bulk Upload (BU) using update tags
     * @return void
     */
    public function deactivateBenefitFromBU_updateTag($arr_bu_benefits)
    {
        $activePlanLinks = $this->activePlanLinks;
        $arr_bu_benefit_names = Arr::pluck($arr_bu_benefits, 'name');
        foreach ($activePlanLinks as $key => $plan_link) {
            if (in_array($plan_link->benefit->code, $arr_bu_benefit_names)) {
                $plan_link->update(['status' => 'cancelled']);
            }
        }
    }

    /**
     * Set Benefits from Bulk Upload (BU)
     * @param mixed $arr_bu_benefits
     * @return Members|\Illuminate\Database\Eloquent\Builder
     */
    public function setBenefitsFromBU($arr_bu_benefits)
    {
        $company_id = $this->company->id;

        $arr_bu_benefit_names = Arr::pluck($arr_bu_benefits, 'name');
        $obj_benefits = Benefit::where('company_id', $company_id)->get();

        foreach ($obj_benefits as $obj_benefit) {
            if (in_array($obj_benefit->code, $arr_bu_benefit_names)) {
                if (is_null($obj_benefit->currentPeriod)) {
                    continue;
                }

                $should_create = $arr_bu_benefits[$obj_benefit->code]['amount'] ?? false;
                $new_plan_link = $this->activePlanLinks()->create([
                    'member_id' => $this->id,
                    'benefit_period_id' => $obj_benefit->currentPeriod->id,
                    'status' => 'active',
                    'enrollment_date' => now(),
                    'valid_until' => $obj_benefit->currentPeriod->expiration_date
                ]);
                // Should Create when an 'amount' key is in the sheet uploaded
                if (is_numeric($should_create) && !is_bool($should_create)) {
                    $remaining = $arr_bu_benefits[$obj_benefit->code]['remaining'] ?? 0;
                    if ($arr_bu_benefits[$obj_benefit->code]['remaining']) {
                        $remaining = floatval($arr_bu_benefits[$obj_benefit->code]['remaining']);
                    }
    
                    $used = $arr_bu_benefits[$obj_benefit->code]['used'] ?? 0;
                    if ($arr_bu_benefits[$obj_benefit->code]['used']) {
                        $used = floatval($arr_bu_benefits[$obj_benefit->code]['used']);
                    }
                    $category_name = $obj_benefit->categories->first();
                    $new_plan_link->planBuckets()->create([
                        'member_plan_link_id' => $new_plan_link->id,
                        'coverage_type' => $category_name,
                        'allocated_limit' => $arr_bu_benefits[$obj_benefit->code]['amount'] ?? 0,
                        'used_limit' => $used,
                        'remaining_limit' => $remaining,
                    ]);
                } else {
                    // get values from database to set amount
                    $categories = $obj_benefit->categories;
                    foreach ($categories as $category) {
                        try {
                            $new_plan_link->planBuckets()->create([
                                'member_plan_link_id' => $new_plan_link->id,
                                'coverage_type' => $category->name,
                                'allocated_limit' => $category->amount,
                                'used_limit' => 0,
                                'remaining_limit' => $category->amount,
                            ]);
                        }  catch (\Throwable $e) {
                            logger()->error('Bucket creation failed', [
                                'message' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    }
                }
            }
        }

        return $this->with('planLink.buckets');
    }

    /**
     * Set Benefits from Bulk Upload (BU) for Rehired Employees
     * @param mixed $arr_bu_benefits
     * @return Members|\Illuminate\Database\Eloquent\Builder
     */
    public function setBenefitsFromBUForRehired($arr_bu_benefits)
    {
        $company_id = $this->company->id;

        $arr_bu_benefit_names = Arr::pluck($arr_bu_benefits, 'name');
        $obj_benefits = Benefit::where('company_id', $company_id)->get();
        
        foreach ($obj_benefits as $obj_benefit) {
            $should_pass_for_bucket_creation = false;
            $past_buckets_balance = 0;

            // get value of the cancelled bucket to get remaining balance
            $cancelledPlanLink = $this->planLink()->with([
                'planBuckets'
            ])->where('status', '!=', 'active')
            ->whereHas('benefitPeriod.benefit', function ($q) use ($obj_benefit)  {
                return $q->where('code', $obj_benefit->code);
            })
            ->whereHas('benefitPeriod', function ($q) {
                return $q->where('is_current', 1);
            })->first();

            if (is_null($cancelledPlanLink)) {
                $should_pass_for_bucket_creation = true;
            } else {
                $past_buckets_balance = floatval($cancelledPlanLink->planBuckets->sum('remaining_limit') ?? 0);
            }

            if (in_array($obj_benefit->code, $arr_bu_benefit_names)) {
                if (is_null($obj_benefit->currentPeriod)) {
                    continue;
                }

                $should_create = $arr_bu_benefits[$obj_benefit->code]['amount'] ?? false;
                // create a new plan link
                $new_plan_link = $this->activePlanLinks()->create([
                    'member_id' => $this->id,
                    'benefit_period_id' => $obj_benefit->currentPeriod->id,
                    'status' => 'active',
                    'enrollment_date' => now(),
                    'valid_until' => $obj_benefit->currentPeriod->expiration_date
                ]);
                logger()->info("Setting benefit for member:", [
                    'id' => $this->id,
                    'email' => $this->email,
                    'benefit_code' => $obj_benefit->code,
                    'shouldCreate' => $should_create,
                    'planLink' => $cancelledPlanLink,
                    'totalBalance' => $past_buckets_balance,
                ]);

                // if value is undefined create new bucket
                $categories = $obj_benefit->categories;
                // if member does not have this previous benefit and now there is; then create this benefit
                if ($should_pass_for_bucket_creation) {
                    foreach ($categories as $category) {
                        // create a new plan bucket per category under benefit
                        // balance is fetch from database
                        // if balance is in the excel file uploaded; this will not get the value
                        try {
                            $new_plan_link->planBuckets()->create([
                                'member_plan_link_id' => $new_plan_link->id,
                                'coverage_type' => $category->name,
                                'allocated_limit' => $category->amount,
                                'used_limit' => 0,
                                'remaining_limit' => $category->amount,
                            ]);
                        }  catch (\Throwable $e) {
                            logger()->error('Bucket creation failed', [
                                'message' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    }
                }

                // if value > 0, create new bucket and use the value from arr_bu_benefits
                if ($past_buckets_balance > 0) {
                    if (is_numeric($should_create) && !is_bool($should_create)) {
                        $remaining = $arr_bu_benefits[$obj_benefit->code]['remaining'] ?? 0;
                        if ($arr_bu_benefits[$obj_benefit->code]['remaining']) {
                            $remaining = floatval($arr_bu_benefits[$obj_benefit->code]['remaining']);
                        }

                        $used = $arr_bu_benefits[$obj_benefit->code]['used'] ?? 0;
                        if ($arr_bu_benefits[$obj_benefit->code]['used']) {
                            $used = floatval($arr_bu_benefits[$obj_benefit->code]['used']);
                        }
                        $coverage_type = $categories->first()->name;
                        // create plan bucket
                        $new_plan_link->planBuckets()->create([
                            'member_plan_link_id' => $new_plan_link->id,
                            'coverage_type' => $coverage_type,
                            'allocated_limit' => $arr_bu_benefits[$obj_benefit->code]['amount'] ?? 0,
                            'used_limit' => $used,
                            'remaining_limit' => $remaining,
                        ]);
                    } else {
                        // use previous buckets remaining amount in new buckets
                        $buckets = $cancelledPlanLink->planBuckets;
                        foreach ($buckets as $bucket) {
                            $new_plan_link->planBuckets()->create([
                                'member_plan_link_id' => $new_plan_link->id,
                                'coverage_type' => $bucket->coverage_type,
                                'allocated_limit' => $bucket->remaining_limit,
                                'used_limit' => 0,
                                'remaining_limit' => $bucket->remaining_limit,
                            ]);
                        }
                    }
                } else {
                    $new_plan_link->delete();
                }
                
                // if value of past bucket is 0, do not create new bucket
            }
        }

        return $this;
    }
}
