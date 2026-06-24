<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->company_id,
            'created_at' =>  \Carbon\Carbon::parse($this->created_at)->format('Y-m-d'),
            'code' => $this->code,
            'name' => $this->name,
            'client_id' => $this->client_id,
            'contract_period_start' => \Carbon\Carbon::parse($this->contract_period_start)->format('Y-m-d'),
            'contract_period_end' => \Carbon\Carbon::parse($this->contract_period_end)->format('Y-m-d'),
            'policy_period' => $this->policy_period,
            'policy' => json_decode($this->policy),
            'account_officer' => json_decode($this->account_officer),
            'status' => $this->status,
            'contract_status' => $this->contract_status,
            'effectivity_date' => $this->effectivity_date,
            'created_by' => $this->created_by,
            'coordinators' => $this->coordinators,
            'remaining_days' =>  $this->when($this->remaining_days, $this->remaining_days),
            'contract_id' =>  $this->when($this->contract_id, $this->contract_id),
            'isNotified' => $this->isNotified,
            'form_version' => $this->form_version,
            'email_version' => $this->email_version,
            'logo_path' => $this->logo_path,
            'benefit_access' => $this->benefit_access ? json_decode($this->benefit_access) : [],
            'support_sentence_template' => $this->support_sentence_template,
            'support_email_sentence_template' => $this->support_email_sentence_template,
            'support_emails' => $this->support
        ];
    }
}
