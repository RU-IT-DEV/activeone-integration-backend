<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\MemberPlanLink;

class MemberProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $member = $this;
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
                    'enrollment_date' => $item->enrollment_date,
                    'used_amount' => $used_amount,
                    'remaining_amount' => $total_remaining,
                    'total_amount' => number_format($total_amount, 2),
                    'valid_until' => $item->valid_until,
                    'plan_buckets' => $planBuckets_collection->toArray(),
                    'status' => $item->status,
                ];
            });;

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
                $$var['value'] = 0.00;
                if ($$var['amount'] > 0) {
                    $$var['value'] = round($$var['amount'] / $item['allocated_amount'] * 100, 2);
                }
            }

            // ORDERING RULE:
            if ($pending_claims_amount < $remaining_amount) {
                // Approved → Pending → Remaining
                $orderedPie = [$approved, $pending, $remaining];
            } else {
                // Approved → Remaining → Pending
                $pending['value'] = max(0, ($pending['value'] ?? 0) - ($approved['value'] ?? 0));
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

        return [
            'id' => $this->id,
            'created_at' =>  \Carbon\Carbon::parse($this->created_at)->format('Y-m-d'),
            'flexicare_id' => $this->flexicare_id,
            'company_code' => $this->company_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'suffix' => $this->suffix,
            'payee_code' => $this->payee_code,
            'member_classification' => $this->member_classification,
            'employee_no' => $this->employee_no,
            'birthdate' => $this->birthdate,
            'gender' => $this->gender,
            'civil_status' => $this->civil_status,
            'email' => $this->email,
            'position' => $this->position,
            'salary_grade' => $this->salary_grade,
            'date_hired' => $this->date_hired,
            'deactivation_date' => $this->deactivation_date,
            'division' => $this->division,
            'member_type' => $this->member_type,
            'principal_id' => $this->principal_id,
            'date_endorsed' => $this->date_endorsed,
            'status' => $this->status,
            'company' => json_decode($this->company),
            // 'plan_link' => PlanLinkResource::collection($this->planLink),
            // 'pending_claims_count' => $this->pending_claims_count,
            // 'bank_details' => BankDetailsResource::collection($this->whenLoaded('bankDetails')),
            // Return empty array if no plan links
            'plan_link' => $benefits,
            // Return 0 or empty — using empty array as requested
            'pending_claims_count' => $this->pending_claims_count ?? 0,
            // Return empty array if no bank details loaded
            'bank_details' => $this->bankDetails && $this->bankDetails->isNotEmpty()
                ? BankDetailsResource::collection($this->bankDetails)
                : [],
        ];
    }
}
