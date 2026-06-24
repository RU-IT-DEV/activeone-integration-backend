<?php

namespace App\Helper;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ClaimsHelper
{
    public static function processSubClaims(array $subClaims)
    {
        $totalApprovedAmount = 0;
        $totalRejectedAmount = 0;

        $processedSubClaims = collect($subClaims)->map(function ($sub) use (&$totalApprovedAmount, &$totalRejectedAmount) {
            $approved = floatval($sub['approved_amount']);
            $amount = floatval($sub['amount']);
            $rejected = $amount - $approved;

            $totalApprovedAmount += $approved;
            $totalRejectedAmount += $rejected;

            return [
                ...$sub,
                'approved_amount' => $approved,
                'rejected_amount' => $rejected,
                'status' => $approved > 0
                    ? ($rejected > 0 ? 'partially_approved' : 'approved')
                    : 'rejected',
            ];
        });

        return [
            'sub_claims' => $processedSubClaims->toArray(),
            'total_approved_amount' => $totalApprovedAmount,
            'total_rejected_amount' => $totalRejectedAmount
        ];
    }

    public static function mapMemberBenefits(array $memberData): array
    {
        $data = [];
        $arr_keys = array_keys($memberData);

        array_filter($arr_keys, function ($key) use ($memberData, &$data) {
            if (str_contains($key, 'B:')) {
                $key = str_replace('-Amount', '', $key);
                $key = str_replace('-UsedAmount', '', $key);
                $key = str_replace('-RemainingAmount', '', $key);
                
                $benefitName = str_replace('B:', '', $key);

                if ($memberData["B:$benefitName"] == "No") {
                    return false; // Skip if the benefit is marked as "No"
                }

                if (!isset($memberData["B:$benefitName-Amount"])) {
                    $data[$benefitName] = [
                        'name' => $benefitName
                    ];

                    return true;
                }
                
                $data[$benefitName] = [
                    'name' => $benefitName,
                    'used' => floatval($memberData["B:$benefitName-UsedAmount"] ?? 0),
                    'remaining' => floatval($memberData["B:$benefitName-RemainingAmount"] ?? 0),
                    'amount' => floatval($memberData["B:$benefitName-Amount"] ?? 0)
                ];
            }
        });

        return $data;
    }

    /**
     * Summary of mapClaimData_for_BQPush
     * used to map claim data for BigQuery push
     * @param mixed $claim_response
     * @return array
     */
    public static function mapClaimData_for_BQPush ($claim_response)
    {
        $member = $claim_response->member;

        $claim = $claim_response->claim;

        $sub_claims = $claim->subClaimDetails;

        $user = User::where('email', $claim_response->adjudicated_by)->first();

        $plan = $claim_response->planLink->benefitPeriod->benefit;

        $freshdesk_id = $claim->freshdesk_claim_id ?? '';
        $freshdesk_id = trim((string) $freshdesk_id);
        $claimId = $claim->claim_id;
        if (!empty($freshdesk_id)) {
            $claimId = $freshdesk_id;
        } else if ($freshdesk_id == 'null') {
            $claimId = $claim->claim_id;
        }

        $gen_schema = [
            "PROCESSOR" => "$user->name",
            "CLAIM_NUMBER" => "$claimId",
            "EMAIL_ADDRESS" => "$member->email", // member email
            "RECEIVED_DATE" => Carbon::parse($claim->received_date)->format('n/j/Y'),
            "PROCESSED_DATE" => Carbon::parse($claim_response->created_at)->format('n/j/Y H:i:s'),
            "FULL_NAME" => "$member->first_name $member->last_name",
            "EMAIL_SENT" => "EMAIL SENT",
            "TOTAL_AMOUNT" => $claim->total_amount,
            "DATE_APPEND" => Carbon::parse($claim_response->created_at)->format('Y-m-d'),
        ];

        $arr_pushToBq = [];
        if ($claim->type == 'fsa') {
            $arr_pushToBq = self::appendFSASchema($gen_schema, $member, $plan, $claim, $claim_response, $sub_claims);
        } else if ($claim->type == 'choicepot') {
            $arr_pushToBq = self::appendCPSchema($gen_schema, $member, $plan, $claim, $claim_response, $sub_claims);
        }

        return Arr::map($arr_pushToBq, fn ($item) => [
            'user_id' => Auth::id(),
            'member_claim_id' => $claim->id,
            'data' => json_encode($item),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Summary of appendFSASchema
     * used to append FSA schema to the general schema for BigQuery Push
     * @param mixed $general_schema
     * @param mixed $member
     * @param mixed $plan
     * @param mixed $claim
     * @param mixed $claim_response
     * @param mixed $sub_claims
     * @return array[]
     */
    private static function appendFSASchema ($general_schema, $member, $plan, $claim, $claim_response, $sub_claims)
    {
        $result = [];
        $total_ytd_fsa = $claim_response->bucket->allocated_limit;
        $fsa_schema = [
            "DATE_OF_HIRE" => Carbon::parse($member->date_hired)->format('n/j/Y'),
            "SALARY_GRP" => "$member->salary_grade",
            "TAXABLE_YEAR" => Carbon::now()->format('Y'),
            "TOTAL_YTD_FSA" => $total_ytd_fsa,
            "DATE_SUBMITTED" => Carbon::parse($claim_response->created_at)->format('n/j/Y'),
            "RECEIPT_AMOUNT" => normalizeFloat($claim->total_amount),
            "REMARKS" => "$claim_response->remarks",
            "REQUESTOR" => "$claim_response->adjudicated_by",
            "OTHERS_7" => null,
            "OTHERS_8" => null,
            "DATE_UPDATED" => Carbon::now()->format('Y-m-d\TH:i:s.u')
        ];
        $final_schema = array_merge($general_schema, $fsa_schema);

        foreach ($sub_claims as $sub_claim) {
            $transaction_id = "$claim->claim_id-$sub_claim->id";

            $final_schema["PURPOSE"] = "$sub_claim->category";
            $final_schema["DATE_OF_EXPENSE"] = Carbon::parse($sub_claim->receipt_date)->format('n/j/Y');
            $final_schema["MERCHANT"] = "$sub_claim->vendor_name";
            $final_schema["TRANSACTION_ID"] = "$transaction_id";
            $final_schema["TICKET_NO"] = "WEBAPP$transaction_id";
        }
        $result[] = $final_schema;

        return $result;
    }

    /**
     * Summary of appendCPSchema
     * used to append FSA schema to the general schema for BigQuery Push
     * @param mixed $general_schema
     * @param mixed $member
     * @param mixed $plan
     * @param mixed $claim
     * @param mixed $claim_response
     * @param mixed $sub_claims
     * @return array[]
     */
    private static function appendCPSchema ($general_schema, $member, $plan, $claim, $claim_response, $sub_claims)
    {
        $result = [];
        $bucket = $claim_response->planLink->planBuckets->first();
        $cp_schema = [
            "DIVISION_NAME" => "$member->division",
            "EXPENSE_TYPE" => "$plan->name",
            // push from sub claim foreach
            "DISAPPROVED_AMOUNT" => normalizeFloat($claim_response->rejected_amount),
            "TOTAL_APPROVED_AMOUNT" => normalizeFloat($claim_response->approved_amount),
            "EMAIL_SENT" => "EMAIL SENT",
            "ACCOUNT" => $member->bankDetails->first()->account_number ?? '',
            "NAME_OF_BANK" => $member->bankDetails->first()->bank_name ?? '',
            "START_BALANCE" => $bucket->allocated_limit ?? 0,
            "ACCOUNT_NAME" => $member->bankDetails->first()->account_name ?? '',
            "OTHERS_1" => "NEW", // NEW / UPDATE
            "REQUESTOR" => "$claim_response->adjudicated_by" // REQUESTOR email
        ];

        foreach ($sub_claims as $key => $sub_claim) {
            if ($key > 0) {
                $cp_schema["DISAPPROVED_AMOUNT"] = null;
                $cp_schema["TOTAL_APPROVED_AMOUNT"] = null;
                $general_schema["TOTAL_AMOUNT"] = null;
            }
            $final_schema = array_merge($general_schema, $cp_schema);
            $partially = [
                'APPROVED_AMOUNT' => $sub_claim->amount - $sub_claim->approved_amount,
                'DISAPPROVED_AMOUNT' => $sub_claim->amount - ($sub_claim->amount - $sub_claim->approved_amount),
            ];

            $transaction_id = "$claim->claim_id-$sub_claim->id";

            $arr = [
                "CATEGORY" => "$sub_claim->category",
                "SUBCATEGORY" => "$sub_claim->sub_category",
                "ACTIVITIES_ITEMS_BENEFITS" => "$sub_claim->activities_or_items",
                "DESCRIPTION" => "$sub_claim->description",
                "BENEFICIARY" => "$sub_claim->relation_to_employee",
                "DEPENDENT_CLASSIFICATION" => "NOT APPLICABLE",
                "RECEIPT_DATE" => "$sub_claim->receipt_date",
                "OR_NUMBER" => "$sub_claim->or_number",
                "AMOUNT" => $sub_claim->amount,
                "APPROVED_AMOUNT" => $sub_claim->approved_amount,
                "PARTIALLY_APPROVED" => normalizeFloat($partially['APPROVED_AMOUNT']),
                "PARTIALLY_DISAPPROVED" => normalizeFloat($partially['DISAPPROVED_AMOUNT']),
                "REJECTION_REASON" => "$sub_claim->rejection_reason",
                "TRANSACTION_ID" => $transaction_id,
                "TICKET_NO" => "WEBAPP$transaction_id", // TICKET NUMBER
                "DATE_UPDATED" => Carbon::now()->format('Y-m-d\TH:i:s.u'),
                "TIME_SAVED" => Carbon::parse($claim_response->created_at)->format('H:i:s'),
                "DURATION" => Carbon::parse($claim_response->created_at)->diffForHumans($claim->received_date),
            ];

            $result[] = array_merge($final_schema, $arr);
        }

        return $result;
    }
}

function normalizeFloat($value)
{
    $value = $value === '' ? 0 : (float) $value;
    return round($value, 2); 
}