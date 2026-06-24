<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\MemberBankDetail;
use App\Models\Members;
use Illuminate\Http\Request;

class BankDetailsController extends BaseController
{
    public function store (Request $request, Members $member)
    {
        $this->validate($request, [
            'bank_name' => 'required|string',
            'account_number' => 'required|numeric',
            'account_name' => 'required|string'
        ]);

        try {
            $data = $request->all();
            $new_bank_detail = null;
            if (!array_key_exists("id", $data)) {
                $new_bank_detail = $member->bankDetails()->create($data);
            } else {
                $bank = MemberBankDetail::find($data['id']);
                $bank->update($data);
                $bank->save();
                $new_bank_detail = $bank;
            }
            return $this->successLog('created', 'MemberBankDetail')
                ->sendResponse($new_bank_detail, "Bank account added successfully.");
        } catch (\Exception $e) {
            return $this->errorLog('create', 'MemberBankDetail')
                ->sendError($e->getMessage(), [], 400);
        }
    }
}
