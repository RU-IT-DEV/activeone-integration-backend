<?php

namespace App\Http\Controllers\Api\Members;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class BankDetailsController extends BaseController
{
    public function show ()
    {
        $member = Auth::guard('member_api')->user();
        if ($member->bankDetails->count() == 0) {
            return $this->sendError("No bank details retrieved.", [], 400);
        } else {
            return $this->sendResponse($member->bankDetails, "Successfully retrieved bank details");
        }
    }

    public function store (Request $request)
    {
        $this->validate($request, [
            'bank_name' => 'required|string',
            'account_number' => 'required|numeric',
            'account_name' => 'required|string'
        ]);

        $member = Auth::guard('member_api')->user();

        try {
            $data = $request->all();
            $new_bank_detail = $member->bankDetails()->create($data);
            return $this->successLog('created', 'MemberBankDetail')
                ->sendResponse($new_bank_detail, "Bank account added successfully.");
        } catch (\Exception $e) {
            return $this->errorLog('create', 'MemberBankDetail')
                ->sendError($e->getMessage(), [], 400);
        }
    }
}
