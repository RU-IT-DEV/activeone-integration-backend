<?php

namespace App\Http\Controllers\Api\Members;

use App\Http\Controllers\Api\BaseController as Controller;
use App\Models\MemberPlanLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Roles;
use App\Models\Members;
use App\Models\BenefitCategoryOptions;
use App\Http\Controllers\Api\FileSystemController;

class MemberController extends Controller
{
    public function show (Request $request) {
        // $member = Members::with(['activePlanLinks' => function ($query) {
        //         $query->with(['benefit', 'planActiveBuckets']);
        //     }, 'inactivePlanLinks' => function ($query) {
        //         $query->with(['benefit', 'planBuckets']);
        //     }, 'company' => function ($query) {
        //         $query->select('id', 'code', 'name');
        //     }]);
        
        # THIS QUERY GETS ONLY reimbursement benefits
        $member = Members::with([
            'activePlanLinks' => function ($query) {
                $query->with(['benefit.categoryOptions', 'planActiveBuckets']);
            },
            'inactivePlanLinks' => function ($query) {
                $query->with(['benefit', 'planBuckets']);
            },
            'company' => function ($query) {
                $query->select('id', 'code', 'name', 'logo_path', 'form_version', 'support_sentence_template');
            },
            'company.support',
            'defaultBankDetail' => function ($query) {
                $query->select('member_id', 'bank_name', 'account_number', 'account_name');
            }
        ]);


        $member = $member->find(Auth::guard('member_api')->user()->id);
        $email = explode('@', $member->email);
        $str_rolename = "member@" . $email[1];
        $role = Roles::where('name', $str_rolename)->first();
        $member->role = $str_rolename;
        $member->user_type = "member";
        if ($role)
            $member['navigations'] = $role->navigations;
        else
            $member['navigations'] = [];
        
        return $this->sendResponse($member, "Details fetched successfully.");
    }

    public function getBenefitCategoryOptionDetailById (Request $request, $benefit_category_option) {
        $data = BenefitCategoryOptions::where('name', $benefit_category_option)->first();

        $member = Auth::guard('member_api')->user();
        $pending_claims = $member->claims()->where('coverage', $benefit_category_option)
            ->where('status', 'Pending')->count();
        $processed_claims = $member->claims()->where('coverage', $benefit_category_option)
            ->where('status', '!=', 'Pending')->count();
        $pending_claims_amount = $member->claims()->where('coverage', $benefit_category_option)
            ->where('status', 'Pending')->get()->sum('total_amount');

        $description = 'No description.';
        if (strtolower($benefit_category_option) == 'uflex') {
            $description = "Pool of credits that may be used for other benefits claim.";
        }
        if (!is_null($data)) {
            $description = $data->description;
        }
        return $this->sendResponse([
            'pending_claims' => $pending_claims,
            'pending_claims_amount' => $pending_claims_amount,
            'processed_claims' => $processed_claims,
            'description' => $description
        ], "Details fetched successfully.");
    }

    public function getBenefitsUsage () {
        $member = Auth::guard('member_api')->user();
        
        $planLinks = MemberPlanLink::with(['planBuckets', 'benefit'])
            ->where('member_id', $member->id)
            ->get()
            ->map(function ($item, $key) {
                $planBuckets_collection = collect($item->planBuckets);
                $total_amount = $planBuckets_collection->sum('allocated_limit');
                $total_remaining = $planBuckets_collection->sum('remaining_limit');
                $used_amount = $planBuckets_collection->sum('used_limit');
                $coverage_types = $planBuckets_collection->pluck('coverage_type')->toArray();
                return [
                    'id' => $item->id,
                    'coverage_type' => $coverage_types,
                    'name' => $item->benefit->name,
                    'benefit_id' => $item->benefit_id,
                    'allocated_amount' => $total_amount,
                    'used_amount' => $used_amount,
                    'remaining_amount' => $total_remaining,
                    'total_amount' => number_format($total_amount, 2),
                    'valid_until' => $item->valid_until
                ];
            });

        $monthlyUsage = $member->monthlyClaimsUsage;
        $benefits = $planLinks->map(function ($item) use ($monthlyUsage, $member) {
            $id = $item['id'];
            $monthlyUse = $monthlyUsage->filter(function ($row) use ($id) {
                return $row->member_plan_links_id == $id;
            });

            $months = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];
            // Assuming $monthlyUse is a Collection of objects with ->month and ->total_amount
            $usage = collect($months)->map(function ($m) use ($monthlyUse) {
                $found = collect($monthlyUse)->firstWhere('month', $m);
                return $found ? (float) $found->total_amount : 0;
            });

            // sums
            $processed_claims_amount = $item['used_amount'];
            $plan_link = MemberPlanLink::find($item['id']);
            $pending_claims_amount = $plan_link->memberClaims()
                ->where('member_claims.member_id', $member->id)
                ->where('status', 'Pending')->get()->sum('total_amount');

            // counts
            $pending_claims = $plan_link->memberClaims()
                ->where('member_claims.member_id', $member->id)
                ->where('status', 'Pending')->count();
            $processed_claims = $plan_link->memberClaims()
                ->where('member_claims.member_id', $member->id)
                ->where('status', '!=', 'Pending')
                ->where('status', '!=', 'Rejected')
                ->count();
            $rejected_claims =  $plan_link->memberClaims()
                ->where('member_claims.member_id', $member->id)
                ->where('status', 'Rejected')
                ->count();

            $item['usage'] = $usage;
            $remaining_amount = max(0, $item['allocated_amount'] - $processed_claims_amount);

            $item['pie'] = [];

            $approved      = [
                "key" => 1,
                "title" => "Approved",
                "amount" => $processed_claims_amount
            ];

            $remaining     = [
                "key" => 3,
                "title" => "Remaining",
                "amount" => $remaining_amount
            ];

            $pending       = [
                "key" => 2,
                "title" => $pending_claims_amount > $remaining_amount ? "Overflow Pending" : "Pending",
                "amount" => $pending_claims_amount
            ];

            // Apply percentage
            foreach (['approved','pending','remaining'] as $var) {
                $$var['value'] = round($$var['amount'] / $item['allocated_amount'] * 100, 2);
            }

            // ORDERING RULE:
            if ($pending_claims_amount < $remaining_amount) {
                // Approved → Pending → Remaining
                $orderedPie = [$approved, $pending, $remaining];
            } else {
                // Approved → Remaining → Pending
                $pending['value'] -= $approved['value'];
                $orderedPie = [$approved, $remaining];
                $item['overflow'] = $pending;
            }

            $item['pie'] = $orderedPie;

            $item['counts'] = [
                'pending' => $pending_claims,
                'approved' => $processed_claims,
                'rejected' => $rejected_claims,
            ];
            return $item;
        });
        return $this->sendResponse($benefits, "Success");
    }

    public function updateProfileImage(Request $request) {
        $member = Auth::guard('member_api')->user();

        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $file_system = new FileSystemController(); 
            $file_path = $file_system->filesystem($file, "profile", $member->id, "member");#save file

            // Update member's profile image path
            $member->profile_image_path = $file_path;
            $member->save();

            return $this->sendResponse(['profile_image_path' => $file_path], "Profile image updated successfully.");
        } else {
            return $this->sendError("No profile image uploaded.", [], 400);
        }
    }
}
