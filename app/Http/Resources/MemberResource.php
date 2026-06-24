<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\PlanLinkResource;
use App\Http\Resources\BankDetailsResource;

class MemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
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
            'plan_link' => $this->planLink && $this->planLink->isNotEmpty()
                ? PlanLinkResource::collection($this->planLink)
                : [],
            // Return 0 or empty — using empty array as requested
            'pending_claims_count' => $this->pending_claims_count ?? 0,
            // Return empty array if no bank details loaded
            'bank_details' => $this->bankDetails && $this->bankDetails->isNotEmpty()
                ? BankDetailsResource::collection($this->bankDetails)
                : [],
        ];
    }
}
