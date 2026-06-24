<?php

namespace App\Http\Controllers\Api\Benefits;

use App\Dispatchers\JobDispatcher;
use App\Http\Controllers\Api\BaseController;
use App\Models\Benefit;
use App\Models\BenefitCategories;
use App\Models\BenefitPeriod;
use App\Models\Company;
use App\Models\Members;
use App\Models\MemberPlanBucket;
use App\Models\MemberPlanLink;
use App\Rules\BenefitPeriodRenewalStartEnd;
use App\Http\Resources\TinyMemberResource as MemberResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessDeactivateBenefitPeriod;
use Carbon\Carbon;

class BenefitRenewalController extends BaseController
{
    public function store (Request $request, Benefit $benefit)
    {
        $company = Company::find($benefit->company_id);
        $members_count = Members::where('company_code', $company->code)->where('status', 'active')->count();

        $validated = $request->validate([
            'benefit_id' => 'required|exists:benefits,id',
            'benefit_period_id' => 'required|exists:benefit_periods,id',
            'period' => [
                'required','array', new BenefitPeriodRenewalStartEnd
            ],
            'period.start' => 'required|date|after:current_benefit_date_start',
            'period.end' => 'required|date|after:period.start',
            'option' => 'required|string',
            'members' => [
                $members_count > 0 ? 'required' : 'nullable',
                'array'
            ],
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:benefit_categories,id',
            'categories.*.name' => 'required|string',
            'categories.*.amount' => 'required|numeric|min:1'
        ]);

        try {
            DB::beginTransaction();
            // create new row for benefit_period
            $benefitPeriod = BenefitPeriod::create([
                'benefit_id' => $validated['benefit_id'],
                'effectivity_date' => $validated['period']['start'],
                'expiration_date' => $validated['period']['end'],
                'is_current' => true
            ]);

            if ($members_count > 0) {
                // get members
                $members = Members::whereIn('id', $validated['members'])->get();
                $categories = $validated['categories'];
                // set each member new plan links connected to benefit_period
                foreach ($members as $member) {
                    $memberPlanLink = $member->planLink()->create([
                        'benefit_period_id' => $benefitPeriod->id,
                        'enrollment_date' => $benefitPeriod->effectivity_date,
                        'valid_until' => $benefitPeriod->expiration_date,
                        'status' => 'active'
                    ]);
    
                    foreach ($categories as $category) {
                        $newBucket = [
                            'coverage_type' => $category['name'],
                            'allocated_limit' => $category['amount'],
                            'used_limit' => 0,
                            'remaining_limit' => $category['amount']
                        ];
    
                        $existingBucket = MemberPlanBucket::where('member_plan_link_id', $memberPlanLink->id)
                            ->where('coverage_type', $category['name'])
                            ->first();
    
                        if ($existingBucket && $validated['carry_over']) {
                            $newBucket['allocated_limit'] += $existingBucket->remaining_limit;
                        }
    
                        $memberPlanLink->planBuckets()->create($newBucket);
                    }
                }
            }

            DB::commit();

            if ($benefitPeriod) {
                $expiration = Carbon::parse($benefitPeriod->expiration_date)->endOfDay();
                JobDispatcher::dispatch(
                    new ProcessDeactivateBenefitPeriod($benefitPeriod),
                    $expiration
                );
            }

            return $this->sendResponse([], 'Benefit renewed successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Benefit renewal failed', $e->getMessage(), 500);
        }
    }

    public function getBenefitMembers (Benefit $benefit) {
        $benefitPeriods = $benefit->periods->first();

        $company = Company::find($benefit->company_id);

        $benefit_members = $benefitPeriods->members;
        $benefit_members_ids = $benefit_members->pluck('id')->toArray();
        $members = Members::where('company_code', $company->code)->where('status', 'active')->get();

        $response = [
            'benefit_members' => MemberResource::collection($benefit_members),
            'selected' => $benefit_members_ids,
            'list' => MemberResource::collection($members)
        ];

        return $this->sendResponse($response, "Successfully retrieved Members tagged to this Benefit.");
    }
}
