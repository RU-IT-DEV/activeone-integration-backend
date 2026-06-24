<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use Illuminate\Http\Request;
use App\Models\ClaimCategory;
use App\Models\RejectionReason;

class VlookupController extends BaseController
{
    public function byTypeClaimCategory($type)
    {
        $categories = ClaimCategory::with('subcategories')
            ->where('claim_type', $type)
            ->get();

        $message = "Categories successfully retrieved.";
        
        return $this->sendResponse(
            $categories,
            $message
        );
    }

    public function getRejectionReason ()
    {
        // ✅ Order reasons alphabetically (optional)
        $rejectionReasons = RejectionReason::orderBy('reason', 'asc')->get();

        $message = "Rejection reasons successfully retrieved.";

        return $this->sendResponse(
            $rejectionReasons,
            $message
        );

    }
}