<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanLinkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        // Group the plan_buckets by coverage_type
        $groupedPlanBuckets = $this->planBuckets->groupBy(function ($item) {
            return $item->coverage_type == 'uflex' ? 'uflex' : 'core';
        });

        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'member_id' => $this->member_id,
            'benefit_period_id' => $this->benefit_period_id,
            'enrollment_date' => $this->enrollment_date,
            'valid_until' => $this->valid_until,
            'status' => $this->status,
            'benefit' => json_decode($this->benefitPeriod->benefit),
            'plan_buckets' => [
                'uflex' => $groupedPlanBuckets->get('uflex', []),
                'core' => $groupedPlanBuckets->get('core', []),
            ],
        ];
    }
}
