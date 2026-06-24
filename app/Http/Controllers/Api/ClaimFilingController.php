<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MemberClaims;
use App\Models\SubClaimDetail;
use App\Models\MemberClaimsAttachments;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Api\FileSystemController;

class ClaimFilingController extends BaseController
{

    public function storeClaim(Request $request)
    {
        $input_request = $request->all();
        // Step 1: Validate based on type
        $validation = $this->validateClaimPayload($input_request);
        $uploadedAttachments = [];

        // pull all uploaded files for validation
        foreach ($request->file('sub_claims', []) as $key => $subClaimFiles) {
            foreach ($subClaimFiles['receipt'] as $file) {
                array_push($uploadedAttachments, $file);
            }
        }

        // After base validation, check total attachment size
        if (!$validation->fails() && !empty($uploadedAttachments)) {
            $totalSize = array_reduce($uploadedAttachments, function ($carry, $file) {
                return $carry + $file->getSize();
            }, 0);

            if ($totalSize > 33554432) { // 32 MB
                $validation->errors()->add('attachments', 'Total attachments size must not exceed 32 MB.');
            }
        }

        if ($validation->errors()->any()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validation->errors()
            ], 422); // Use 422 Unprocessable Entity for validation errors
        }

        DB::beginTransaction();
        try {

            // Create the main claim record
            $mainClaim = MemberClaims::create($request->claim);
            $type = $request->claim['type'];

            $prefix = 'CLM';
            $prefix = config('claim.type_abbreviations')[$type] ?? 'CLM';
            $mainClaim->claim_id = $prefix . '' . str_pad($mainClaim->id, 6, '0', STR_PAD_LEFT);
            $mainClaim->version = 'v2';
            $mainClaim->save();

            $subClaims = $request->sub_claims;

            $file_system = new FileSystemController();
            $main_folder = "member_claims";

            foreach ($subClaims as $index => &$subClaim) {
                unset($subClaim['receipt']);
                $subClaimDetail = $mainClaim->subClaimDetails()->create($subClaim);

                // Save file if reimbursement
                if ($request->hasFile("sub_claims.$index.receipt")) {

                    $files = $request->file("sub_claims.$index.receipt");

                    foreach ($files as $key => $file) {
                        $file_path = $file_system->filesystem(
                            $file,
                            $mainClaim->claim_id,
                            $subClaim['category'] ?? 'Uncategorized',
                            $main_folder
                        );

                        $subClaimDetail->attachments()->create([
                            'filepath' => $file_path
                        ]);
                    }
                }
            }

            // Manually log the audit entry
            $mainClaim->logAudit('created', []);

            // Commit the transaction if no errors occur
            DB::commit();
            return $this->sendResponse([
                "name" => Auth::user()->email,
            ], "Claim filed successfully. Claim No: {$mainClaim->claim_id}");
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError("Server Error.", $th->getMessage());
        }
    }

    public function updateClaim(Request $request, $id)
    {
        $input_request = $request->all();
        $input_request['sub_claims'] = $request->input('claim.sub_claims', []);
        $input_request['claim']['member_id'] = (int) $input_request['claim']['member']['id'];

        // Step 1: Validate based on type
        $validation = $this->validateClaimPayload($input_request, $id);

        if ($validation->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validation->errors()
            ], 422); // Use 422 Unprocessable Entity for validation errors
        }

        DB::beginTransaction();
        try {

            // Find the main claim record
            $mainClaim = MemberClaims::findOrFail($id);

            // Capture old sub-claims before deletion
            $oldSubClaims = SubClaimDetail::where('member_claim_id', $mainClaim->id)->get()->map(function ($subClaim) {
                return $subClaim->getOriginal();
            })->toArray();

            $mainClaim->update($input_request['claim']);
            $type = $request->claim['type'];

            // Delete existing sub-claims
            SubClaimDetail::where('member_claim_id', $mainClaim->id)->delete();

            $subClaims = $request->input('claim.sub_claims', []);

            foreach ($subClaims as $index => &$subClaim) {
                $subClaim['member_claim_id'] = $mainClaim->id;
                // Save file if reimbursement
                if ($type === 'reimbursement' && $request->hasFile("sub_claims.$index.receipt")) {

                    $file = $request->file("sub_claims.$index.receipt");

                    $file_system = new FileSystemController();
                    $main_folder = "member_claims";
                    $file_path = $file_system->filesystem(
                        $file,
                        $mainClaim->claim_id,
                        $subClaim['category'] ?? 'Uncategorized',
                        $main_folder
                    );
                    $subClaim['receipt'] = $file_path;
                }
            }
            SubClaimDetail::insert($subClaims);

            // Manually log the audit entry
            $mainClaim->logAudit('updated', $oldSubClaims);

            // Commit the transaction if no errors occur
            DB::commit();
            return $this->sendResponse([
                "name" => Auth::user()->email,
            ], "Claim updated successfully. Claim No: {$mainClaim->claim_id}");
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError("Server Error.", $th->getMessage());
        }
    }

    private function validateClaimPayload(array $data, $claimId = null)
    {
        // Step 1: Base rules
        $baseRules = [
            'claim' => 'required|array',
            'claim.member_id' => 'required|integer',
            'claim.total_amount' => 'required|numeric',
            'claim.freshdesk_claim_id' => $claimId
                ? [
                    'string',
                    'nullable',
                ]
                : [
                    'nullable',
                    'string',
                ],
            'claim.received_date' => 'required|date',
            'claim.member_plan_links_id' => 'nullable',
            'claim.coverage' => 'nullable|string',
            'claim.type' => ['required', Rule::in(['choicepot', 'reimbursement', 'fsa'])],
            'sub_claims' => 'required|array|min:1'
            // 'attachments' => 'nullable|array', // New attachments array
            // 'attachments.*' => 'file|mimes:jpeg,png,jpg,pdf|max:5120', 
        ];

        $validator = Validator::make($data, $baseRules);

        if ($validator->fails()) {
            return $validator;
        }

        // Step 2: Sub-claim rules based on type
        $claimType = $data['claim']['type'] ?? null;

        $subClaimRules = match ($claimType) {
            'choicepot' => [
                'sub_claims.*.category' => 'required|string',
                'sub_claims.*.sub_category' => 'required|string',
                'sub_claims.*.activities_or_items' => 'required|string',
                'sub_claims.*.description' => 'required|string',
                'sub_claims.*.beneficiary' => ['sometimes', 'required', 'string', Rule::in(['Employee', 'Dependent'])],
                'sub_claims.*.relation_to_employee' => 'nullable|string',
                // 'sub_claims.*.receipt_date' => 'required|date',
                'sub_claims.*.receipt_date' => [
                    'required',
                    'date',
                    'before_or_equal:' . now()->endOfYear()->toDateString(),
                ],
                'sub_claims.*.or_number' => 'required|string',
                'sub_claims.*.amount' => 'required|numeric',
                'sub_claims.*.receipt' => 'required',
                'sub_claims.*.receipt.*' => 'file|mimes:jpeg,png,jpg,pdf|max:8192',
            ],
            'reimbursement' => [
                // 'sub_claims.*.category' => 'required|string',
                'sub_claims.*.vendor_name' => 'required|string',
                'sub_claims.*.receipt_date' => [
                    'required',
                    'date',
                    'before_or_equal:' . now()->endOfYear()->toDateString(),
                ],

                // ✅ Allow either a new file OR existing string path
                'sub_claims.*.receipt.*' => [
                    'required',
                    function ($attribute, $value, $fail) {
                            if (!is_string($value) && !($value instanceof \Illuminate\Http\UploadedFile)) {
                                $fail("The {$attribute} must be a file upload or a valid file path string.");
                            }
                        },
                ],
                'sub_claims.*.purpose' => 'required|string',
                'sub_claims.*.parking_location' => 'required|string',
                'sub_claims.*.vehicle_plate_number' => 'required|string',

                // 'sub_claims.*.vendor_tin' => 'required|string',
                // 'sub_claims.*.vendor_address' => 'required|string',
                'sub_claims.*.amount' => 'required|numeric',
                'sub_claims.*.receipt' => 'required'
            ],
            'fsa' => [
                'sub_claims.*.category' => 'required|string',
                'sub_claims.*.vendor_name' => 'required|string',
                'sub_claims.*.receipt_date' => [
                    'required',
                    'date',
                    'before_or_equal:' . now()->endOfYear()->toDateString(),
                ],
                'sub_claims.*.amount' => 'required|numeric',
                'sub_claims.*.receipt' => 'nullable',
                'sub_claims.*.receipt.*' => 'file|mimes:jpeg,png,jpg,pdf|max:8192',
            ],
            default => [],
        };

        return Validator::make($data, array_merge($baseRules, $subClaimRules), [
            'sub_claims.*.receipt_date.required' => 'This sub-claim requires a receipt date.',
            'sub_claims.*.receipt_date.date' => 'This sub-claim must be a valid date.',
            'sub_claims.*.receipt.required' => 'Each sub-claim requires a receipt file.',
            'sub_claims.*.receipt.*.max' => 'Each sub-claim receipt must not exceed 8MB.'
        ]);
    }

    /**
     * Save attachments for main claim
     */
    private function storeAttachments(array $attachments, $mainClaim)
    {
        if (empty($attachments))
            return;

        $category = 'admin-uploaded'; // Default category for admin-uploaded files
        foreach ($attachments as $attachment) {

            // File upload
            $file_system = new FileSystemController();
            $main_folder = "member_claims/{$mainClaim->claim_id}/attachments";
            $file_path = $file_system->filesystem(
                $attachment,
                $mainClaim->claim_id,
                $category,
                $main_folder
            );

            // Save to DB
            MemberClaimsAttachments::create([
                'member_claim_id' => $mainClaim->id,
                'filepath' => $file_path
            ]);
        }
    }
}
