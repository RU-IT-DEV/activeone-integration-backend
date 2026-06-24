<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdjudicateClaimRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('has-access', 'Adjudication-adjudicate');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'claim_id' => [
                'required',
                'integer',
                Rule::exists('member_claims', 'id')->where(fn ($q) => $q->where('status', 'Pending')),
            ],
            'final_status' => 'required|string',
            'adjudicator.remarks' => 'nullable|string',
            'adjudicator.selected_plan' => 'required|integer|exists:member_plan_links,id',
            'adjudicator.selected_bucket' => 'required|integer|exists:member_plan_buckets,id',
            'sub_claim_details' => 'required|array|min:1',
            'sub_claim_details.*.id' => 'required|integer|exists:sub_claim_details,id',
            'sub_claim_details.*.approved_amount' => 'required|numeric|min:0.00',
            'adjudicator.rejection_reason' => 'nullable|string',
            'adjudicator.isConfirmEvenIfNegative' => 'nullable|boolean' // <-- new flag
        ];
    }
}
