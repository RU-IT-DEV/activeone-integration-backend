<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use Illuminate\Http\Request;
use App\Models\ClaimQrScanLog;
use App\Models\MemberClaims;

use App\Mail\ScanQRAcknowledgementMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;


class QRScanTrackingController extends BaseController
{
    // GET /qr-scan-trackings
    public function index()
    {
        return ClaimQrScanLog::with(['memberClaim' => function ($query) {
            return $query->select('id', 'freshdesk_claim_id');
        }])->orderBy('created_at', 'desc')->get();
    }

    // POST /qr-scan-trackings
    public function store(Request $request)
    {
        $request['member_claim_id'] = $request->id ?? null;

        $validated = $request->validate([
            'member_claim_id' => 'required|exists:member_claims,id|unique:claim_qr_scan_logs,member_claim_id',
            'claim_id' => 'required|string',
            'email' => 'nullable|email',
            'employee_name' => 'nullable|string',
            'box_no' => 'nullable|string',
            'is_email_sent' => 'boolean',
            'scanned_at' => 'nullable|date',
            'actual_received_date' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        $validated['scanned_at'] = now('Asia/Manila')->format('Y-m-d H:i:s');
        $validated['is_email_sent'] = false;

        $record = ClaimQrScanLog::create($validated);

        // Send acknowledgement email
        if (!empty($validated['email'])) {
            try {
                $this->sendEmail($record);
                
                $record->update(['is_email_sent' => true]);
            } catch (\Exception $e) {
                Log::error('Claim acknowledgement email failed', [
                    'claim_id' => $validated['claim_id'],
                    'email' => $validated['email'],
                    'error' => $e->getMessage()
                ]);

                return $this->errorLog('sendmail', 'QRScanTracking')->sendError($e->getMessage(), [], 400);
            }
        }

        return $this->successLog('sendmail', 'QRScanTracking')
            ->sendResponse($record->fresh(), 'QR scan tracking created successfully.');
    }

    // GET /qr-scan-trackings/{qr_scan_tracking}
    public function show(ClaimQrScanLog $qrScanTracking)
    {
        return $qrScanTracking->load('memberClaim');
    }

    // PUT /qr-scan-trackings/{qr_scan_tracking}
    public function update(Request $request, ClaimQrScanLog $qrScanTracking)
    {
        $validated = $request->validate([
            'email' => 'nullable|email',
            'employee_name' => 'nullable|string',
            'box_no' => 'nullable|string',
            'is_email_sent' => 'boolean',
            'scanned_at' => 'nullable|date',
            'actual_received_date' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        $qrScanTracking->update($validated);

        return response()->json([
            'message' => 'QR scan tracking updated successfully.',
            'data' => $qrScanTracking
        ]);
    }

    // DELETE /qr-scan-trackings/{qr_scan_tracking}
    public function destroy(ClaimQrScanLog $qrScanTracking)
    {
        $qrScanTracking->delete();

        return response()->json([
            'message' => 'QR scan tracking deleted successfully.'
        ]);
    }

    // Get Claim details
    public function getClaimDetails($claimsId)
    {
        $claimDetails = MemberClaims::with(['member'])
            ->where('claim_id', $claimsId)
            ->first();

        if (!$claimDetails) {
            return $this->sendError('Claim not found.', 404);
        }
        return $this->sendResponse($claimDetails, 'Claim details retrieved successfully.');
    }

    private function sendEmail ($qrScanLog) {
        $claimId = $qrScanLog->claim_id;
        if (!empty($qrScanLog->memberClaim->freshdesk_claim_id)) {
            $claimId = $qrScanLog->memberClaim->freshdesk_claim_id;
        }
        
        Mail::to($qrScanLog->email)
            ->send(new ScanQRAcknowledgementMail(
                $claimId,
                \Carbon\Carbon::parse($qrScanLog->actual_received_date)->format('F d, Y h:i A'),
                $qrScanLog->employee_name ?? 'Valued Member'
            ));

        $qrScanLog->update(['is_email_sent' => true]);
    }

    public function resendEmail(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:claim_qr_scan_logs,id'
        ]);

        $qrScanLog = ClaimQrScanLog::find($request->id);

        if (!$qrScanLog) {
            return $this->sendError('QR scan log not found.', [], 404);
        }

        
        try {
            $this->sendEmail($qrScanLog);

            return $this->successLog('sendmail', 'QRScanTracking')->sendResponse([
                'id' => $qrScanLog->id,
                'is_email_sent' => $qrScanLog->is_email_sent
            ], 'Email resent successfully.');
        } catch (\Exception $e) {
            Log::error('Claim acknowledgement email failed', [
                'claim_id' => $qrScanLog->claim_id,
                'email' => $qrScanLog->email,
                'error' => $e->getMessage()
            ]);

            return $this->errorLog('sendmail', 'QRScanTracking')->sendError($e->getMessage(), [], 400);
        }
    }
}

