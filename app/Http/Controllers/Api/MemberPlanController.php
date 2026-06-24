<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use Illuminate\Http\Request;
use App\Models\Benefit;
use App\Models\Members;
use App\Models\MemberPlanLink;
use App\Models\MemberPlanBucket;


class MemberPlanController extends BaseController
{
    //
    public function getMemberPlans (Request $request, Members $id) {
        // update status of member plan links to expired if any
        $today = now();
        MemberPlanLink::where('valid_until', '<', $today)->update([
            'status' => 'expired'
        ]);

        $memberPlan = [];
        
        if (isset($request->for)) {
            $member = $id;
            $memberPlanPeriods = $member->selectablePeriodsForAdj;
            $memberPlanPeriods->load([
                'benefit'
            ]);
            $memberPlanLinks = $member->planLink->load(['planBuckets'])->toArray();
            
            if ($request->for === "adjudication select plan") {
                foreach ($memberPlanPeriods as $obj_mem_plan_periods) {
                    $benefit_period_id = $obj_mem_plan_periods->id;
                    $arr_mem_plan_link = array_values(array_filter($memberPlanLinks, function ($plan_link) use ($benefit_period_id) {
                        return $plan_link['benefit_period_id'] == $benefit_period_id;
                    }));
                    
                    if (isset($arr_mem_plan_link[0])) {
                        $arr_mem_plan_link = $arr_mem_plan_link[0];
                        $memberPlan[] = [
                            'benefit' => $obj_mem_plan_periods->benefit->toArray(),
                            'plan_buckets' => $arr_mem_plan_link['plan_buckets'],
                            'id' => $arr_mem_plan_link['id'],
                            'status' => $arr_mem_plan_link['status'],
                            'enrollment_date' => $arr_mem_plan_link['enrollment_date'],
                            'valid_until' => $arr_mem_plan_link['valid_until']
                        ];
                    }
                    
                }
            }
        } else {
            $memberPlan = MemberPlanLink::with([
                'benefit',
                'planBuckets'
            ])
            ->where('member_id',$id)
            ->get()
            ->toArray();
        }

        $message = "Users successfully retrieved.";
        return $this->sendResponse(
            $memberPlan,
            $message
        );
    }
}
