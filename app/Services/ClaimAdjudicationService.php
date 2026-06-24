<?php

namespace App\Services;

use App\Models\{
    MemberClaims,
    MemberPlanBucket,
    ClaimsResponse,
    SubClaimDetail
};
use App\Helper\ClaimsHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ClaimAdjudicationService
{
    /**
     * @throws RuntimeException
     */
    public function adjudicate(array $validated, int $claimId): ClaimsResponse
    {
        return DB::transaction(function () use ($validated, $claimId) {

            $result = ClaimsHelper::processSubClaims(
                $validated['sub_claim_details']
            );

            $approved = $result['total_approved_amount'];
            $rejected = $result['total_rejected_amount'];
            
            $bucket = MemberPlanBucket::lockForUpdate()
                ->findOrFail($validated['adjudicator']['selected_bucket']);
            
            $finalStatus = match ($validated['final_status']) {
                'Disapproved' => 'Rejected',
                'Approved' => 'Approved',
                'Partially Approved' => 'Partially approved',
            };

            $this->guardNegativeBalance($bucket, $approved, $validated);

            $this->deductBucket($bucket, $approved);

            $claim = MemberClaims::findOrFail($claimId);
            $claim->update(['status' => $finalStatus]);

            $claimResponse = $this->createClaimResponse(
                $claim,
                $validated,
                $approved,
                $rejected,
                $finalStatus
            );

            logger()->info("Claim Adjudication Service: Adjudicate fxn: ", $result);
            $this->updateSubClaims($result['sub_claims']);

            $claim->claim_logs()->create([
                'from' => 'adjudicator',
                'status' => $finalStatus,
                'log' => $claimResponse
            ]);

            return $claimResponse;
        });
    }

    private function guardNegativeBalance(
        MemberPlanBucket $bucket,
        float $approved,
        array $validated
    ): void {
        $wouldBeNegative = $bucket->remaining_limit - $approved;

        if ($wouldBeNegative < 0 && empty($validated['adjudicator']['isConfirmEvenIfNegative'])) {
            throw new RuntimeException(json_encode([
                'requires_confirmation' => true,
                'current_remaining' => $bucket->remaining_limit,
                'approved_amount' => $approved,
                'resulting_balance' => $wouldBeNegative,
            ]));
        }
    }

    private function deductBucket(MemberPlanBucket $bucket, float $approved): void
    {
        $bucket->increment('used_limit', $approved);
        $bucket->decrement('remaining_limit', $approved);
    }

    private function createClaimResponse(
        MemberClaims $claim,
        array $validated,
        float $approved,
        float $rejected,
        string $status
    ): ClaimsResponse {
        return ClaimsResponse::create([
            'member_claim_id' => $claim->id,
            'member_id' => $claim->member_id,
            'member_plan_links_id' => $validated['adjudicator']['selected_plan'],
            'approved_amount' => $approved,
            'rejected_amount' => $rejected,
            'final_status' => $status,
            'adjudicated_by' => Auth::user()->email,
            'member_plan_bucket_id' => $validated['adjudicator']['selected_bucket'],
            'remarks' => $validated['adjudicator']['remarks'],
            'rejection_reason' => $validated['adjudicator']['rejection_reason'],
        ]);
    }

    private function updateSubClaims(array $subClaims): void
    {
        foreach ($subClaims as $row) {
            SubClaimDetail::where('id', $row['id'])->update([
                'approved_amount' => $row['approved_amount'],
                'rejected_amount' => $row['rejected_amount'],
                'rejection_reason' => $row['rejection_reason'] ?? null,
            ]);
        }
    }
}