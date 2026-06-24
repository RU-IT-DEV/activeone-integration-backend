<?php

namespace App\Http\Controllers\Api;

use App\Dispatchers\JobDispatcher;
use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Requests\AdjudicateClaimRequest;
use App\Jobs\ClaimsPushToBQJob;
use App\Services\ClaimAdjudicationService;
use App\Services\ClaimPdfService;
use App\Services\ClaimMailService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Auth;
use Illuminate\Validation\Rule;
use Validator;
use App\Models\ClaimsResponse;
use App\Models\MemberClaims;
use App\Models\MemberPlanBucket;
use App\Mail\ClaimResponseMail;
use Illuminate\Support\Facades\Mail;
use App\Helper\ClaimsHelper;
use App\Models\SubClaimDetail;
use App\Models\Members;
use App\Models\BQClaimsUpload;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ClaimsResponseController extends BaseController
{
    //

    public function getAllClaimsV2(Request $request) {
        $perPage = $request->get('per_page', 100); 
        $search = $request->get('search');
        $filter = $request->get('filter', []);

        // 👇 Get the current authenticated user
        $user = auth()->user();
        // 👇 Get the list of accessible company IDs (adjust if you're using codes)
        $accessibleCompanyIds = DB::table('user_company_accesses')
            ->where('user_id', $user->id)
            ->pluck('company_id');
        
        $query = MemberClaims::query();

        $claims = $query->with([
            'member.company',
            'response.bucket',
            'qrScanTracking'
            ])
        ->orderBy('id', 'desc');

        // 🔐 Limit by accessible companies
        $query->whereHas('member.company', function ($q) use ($accessibleCompanyIds) {
            $q->whereIn('companies.id', $accessibleCompanyIds);
        });

        # Search
        if ($search) {
            $query->where(function ($query) use ($search) {
                // Match directly on claims table
                $query->where('claim_id', 'like', "%{$search}%")
                    ->orWhere('freshdesk_claim_id', 'like', "%{$search}%");

                $query->orWhereHas('member', function ($q) use ($search) {
                    $q->where(function ($subQuery) use ($search) {
                        $subQuery->where('flexicare_id', 'like', "%{$search}%")
                                 ->orWhere('email', 'like', "%{$search}%");
                    });
                });
            });
        }

        # Apply filters (if set)
        if (!empty($filter)) {
            if (!empty($filter['company'])) {
                $query->whereHas('member.company', function ($q) use ($filter) {
                    $q->whereIn('id', $filter['company']);
                });
            }

            if (!empty($filter['type'])) {
                $query->whereIn('type', $filter['type']);
            }

            if (!empty($filter['status'])) {
                $query->whereIn('status', $filter['status']);
            }
        }

        $paginated = $query->paginate($perPage);

        if ($paginated->isEmpty()) {
            return $this->sendError("No transactions were found.");
        }
    
        return response()->json([
            'data' => $paginated,
            'pagination' => [
                'total' => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'count' => $paginated->count() 
            ],
            'message' =>  "claims fetched successfully.."
        ]);

        return $this->sendResponse($claims, 'claims fetched successfully.');  
    }

    public function getAllClaims(){
        $query = MemberClaims::query();

        $claims = $query->with([
            'member.company',
            'response.bucket'])
        ->orderBy('id', 'desc')
        ->get();

        return $this->sendResponse($claims, 'claims fetched successfully.');  
    }

    public function getClaim($id) { 
        $query = MemberClaims::query();

        $claim = $query->with([
            'member',
            'subClaimDetails.attachments',
            'attachments',
            'response.bucket'])
            ->findOrFail($id);

        return $this->sendResponse($claim, 'claim fetched successfully.');
    }
    # FOR FORM V1
    public function adjudicateClaim(Request $request, $id) {
        $input_request = $request->all();
        $validator = Validator::make($input_request, [
            'approvedAmount' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            return $this->sendError($errors->get('approvedAmount'));
        }
        $finalApprovedAmount = $request->approvedAmount;
        $claimId = $id;

        // Fetch claim
        $claim = MemberClaims::findOrFail($claimId);
        $email_version = $claim->member?->company?->email_version ?? 'default';
        $claim_type = $claim->type;

        //  Validate claim status
        if ($claim->status !== 'Pending') {
            return $this->sendError('Claim has already been adjudicated.');
        }
        // Validate approved amount
        if ($finalApprovedAmount < 0 || $finalApprovedAmount > $claim->total_amount) {
            return $this->sendError('Approved amount must be between 0 and the requested amount.');
        }
        // Begin transaction
        DB::beginTransaction();
        try {  
            # Deduct approved amount from the bucket
            $deductedAmount = $this->deductFromBucket($claim, $finalApprovedAmount);
            if ($deductedAmount === "zero balance") {
                // return $this->sendError('No funds left in the bucket.');
                $deductedAmount = 0;
            }
            # Calculate rejected amount
            $rejectedAmount = $claim->total_amount - $deductedAmount;
            # Determine the final status
            $finalStatus = $this->determineClaimStatus($deductedAmount, $claim->total_amount);

            # Create ClaimResponse
            $claimResponse = ClaimsResponse::create([
                'member_claim_id' => $claim->id,
                'member_id' => $claim->member_id,
                'member_plan_links_id' => $claim->member_plan_links_id,
                'approved_amount' => $deductedAmount,
                'rejected_amount' => $rejectedAmount,
                'final_status' => $finalStatus,
                'adjudicated_by' => Auth::user()->email, // Assuming the current authenticated user is the adjudicator
                'member_plan_bucket_id' => $finalStatus === 'Approved' || $finalStatus === 'Partially approved' ? $this->getBucketIdForClaim($claim) : null, // Link to bucket if  approved
                'remarks' => $request->remarks
            ]);

            // Update the claim status to the adjudicated status
            $claim->status = $finalStatus;
            $claim->save();  

            #claim log
            $claim->claim_logs()->create([
                'claim_id' => $claim->id,
                'from' => 'adjudicator',
                'status' => $finalStatus,
                'log' => $claimResponse
            ]);

            DB::commit();
            
            $claimId = $claimResponse->claim->claim_id;
            if ($claimResponse->claim->freshdesk_claim_id) {
                $claimId = $claimResponse->claim->freshdesk_claim_id;
            }
            // Send the adjudication email
            $pdfContent = null;
            Mail::to($claim->member->email)
            ->send(new ClaimResponseMail(
                $claimId,
                $claimResponse,
                $deductedAmount,
                $rejectedAmount,
                $finalStatus,
                $email_version,
                $claim_type,
                $pdfContent // nullable
            ));

            return $this->sendResponse([
                "name" => Auth::user()->email,
            ], "Claim adjudicated successfully!");
        } 
        catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError("Server Error.", $th->getMessage());
        }
    }
    # FOR FORM V2
    public function adjudicateClaimV2(
        AdjudicateClaimRequest $request,
        int $id,
        ClaimAdjudicationService $service,
        ClaimPdfService $pdfService
    ) {
        $input_request = collect($request->all())->toArray();
        try {
            $claimResponse = $service->adjudicate($input_request, $id);

            $claim = ClaimsResponse::find($claimResponse->id)->claim;
            $claimId = $claim->freshdesk_claim_id ?: $claim->claim_id;
            $email_version = $claim->member?->company?->email_version ?? 'default';
            $claim_type = $claim->type;

            $pdf = $pdfService->generate($claim, $claimId);

            // Map and insert claim data for BigQuery
            $claimData_forPushToBQ = ClaimsHelper::mapClaimData_for_BQPush($claimResponse);
            BQClaimsUpload::insert($claimData_forPushToBQ); // Insert and get the ID for reference
            // Dispatch after response to avoid delaying email
            // or any specific queue you want
            $user = auth()->user();
            JobDispatcher::dispatch(
                new ClaimsPushToBQJob($claimResponse->id, $user->id)
            );

            // Send the adjudication email
            Mail::to($claim->member->email)
                ->send(new ClaimResponseMail(
                    $claimId,
                    $claimResponse,
                    round($claimResponse->approved_amount, 2),
                    round($claimResponse->rejected_amount, 2),
                    $claimResponse->final_status,
                    $email_version,
                    $claim_type,
                    $pdf // nullable
                ));

            return $this->sendResponse(
                ['name' => auth()->user()->email],
                'Claim adjudicated successfully!'
            );

        } catch (\RuntimeException $e) {
            return $this->sendError(
                'Remaining limit will go negative',
                json_decode($e->getMessage(), true)
            );
        }
    }

    public function getCount() {

        $user = auth()->user();
        // Get the user's accessible company IDs
        $accessibleCompanyIds = DB::table('user_company_accesses')
        ->where('user_id', $user->id)
        ->pluck('company_id');

        // Shared base query with member->company filter
        $baseQuery = MemberClaims::whereHas('member.company', function ($query) use ($accessibleCompanyIds) {
            $query->whereIn('companies.id', $accessibleCompanyIds);
        });

         // Count each status with filter
        $pendingCount = (clone $baseQuery)->where('status', 'Pending')->count();
        $approvedCount = (clone $baseQuery)->where('status', 'Approved')->count();
        $partiallyCount = (clone $baseQuery)->where('status', 'Partially approved')->count();
        $rejectedCount = (clone $baseQuery)->where('status', 'Rejected')->count();

        // Return the response as JSON
        $data = [
            'pending' => $pendingCount,
            'approved' => $approvedCount,
            'partially' => $partiallyCount,
            'rejected' => $rejectedCount,
        ];
        return $this->sendResponse($data, "Adjudication dashboard count retrieved successfully.");
    } 

    public function runAdjudicationEngine ($id) {
            
        // Step 1: Get claim and details
        $claim = MemberClaims::findOrFail($id);
        $memberId = $claim->member_id ?? null;
        $plantype = $claim->type ?? null;

        // Step 2: Get member with plans and buckets
        $member = Members::with(['planLink.planBuckets', 'planLink.benefit'])
            ->where(function ($query) use ($memberId) {
                if (is_numeric($memberId)) {
                    $query->where('id', $memberId);
                }
            })
            ->first();

        // Step 3: Initialize results
        $result = [
            'memberValid' => false,
            'activePlan' => false,
            'hasBalance' => false,
        ];

        // if (!$member || $member->status !== 'active') {
        //     return response()->json([
        //         ...$result,
        //         'status' => 'invalid',
        //         'message' => 'Member not found.'
        //     ]);
        // }
        if (!$member || ($member->deactivation_date && Carbon::parse($member->deactivation_date) < now())) {
            $deactivationDate = $member && $member->deactivation_date
                ? Carbon::parse($member->deactivation_date)->format('Y-m-d')
                : null;
        
            return response()->json([
                ...$result,
                'status' => 'invalid',
                'message' => $deactivationDate
                    ? "Member is inactive since {$deactivationDate}."
                    : 'Member not found.'
            ]);
        }

        $result['memberValid'] = true;

        // Get active plans that match the plantype from benefit relation
        $activePlans = $member->planLink->filter(function ($plan) use ($plantype) {
            return $plan->status === 'active' &&
                isset($plan->benefit) &&
                strcasecmp($plan->benefit->type, $plantype) === 0;
        });

        if ($activePlans->isEmpty()) {
            return response()->json(array_merge($result, [
                'status' => 'invalid',
                'message' => 'No active plans found matching the claim type.'
            ]));
        }

        $result['activePlan'] = true;

        // Check if any matching active plan has a bucket with remaining balance
        $hasBalance = $activePlans->some(function ($plan) {
            return collect($plan->planBuckets)->some(function ($bucket) {
                return floatval($bucket->remaining_limit) > 0;
            });
        });

        $result['hasBalance'] = $hasBalance;


        return response()->json([
            ...$result,
            'status' => $hasBalance ? 'valid' : 'invalid',
            'message' => $hasBalance
                ? 'Adjudication passed.'
                : 'Insufficient balance for selected plan type.',
        ]);
    }

    protected function deductFromBucket($claim, $finalApprovedAmount)
    {
        // Find the bucket for the claim's coverage and member plan
        $bucket = MemberPlanBucket::where('member_plan_link_id', $claim->member_plan_links_id)
            ->where('coverage_type', $claim->coverage)
            ->lockForUpdate() // Prevents race conditions
            ->first();

        if (!$bucket) {
            throw new \Exception('No bucket found for the specified coverage and member plan.');
        }

        // Remaining balance
        $remainingBalance = $bucket->remaining_limit;

        if ($remainingBalance <= 0) {
            return "zero balance"; // No funds left in the bucket
        }
        // Deduct approved amount or the maximum available balance
        $amountToDeduct = min($finalApprovedAmount, $remainingBalance);

        $bucket->used_limit += $amountToDeduct;
        $bucket->remaining_limit -= $amountToDeduct;
        $bucket->save();

        return $amountToDeduct;
    }

    protected function determineClaimStatus($approvedAmount, $requestedAmount)
    {
        if ($approvedAmount == 0) {
            return 'Rejected';
        } elseif ($approvedAmount < $requestedAmount) {
            return 'Partially approved';
        } else {
            return 'Approved';
        }
    }

    protected function getBucketIdForClaim($claim)
    {
        $bucket = MemberPlanBucket::where('member_plan_link_id', $claim->member_plan_links_id)
            ->where('coverage_type', $claim->coverage)
            ->first();

        if (!$bucket) {
            throw new \Exception('No bucket found for the specified coverage and member plan.');
        }

        return $bucket->id;
    }

}
