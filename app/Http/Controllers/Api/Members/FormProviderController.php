<?php

namespace App\Http\Controllers\Api\Members;

use App\Http\Controllers\Api\BaseController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormProviderController extends BaseController
{
    public function claimFormProvider () {
        $vendor_list = DB::table("vendor_list")
            ->get()
            ->unique('tin_number')
            ->values()
            ->all();

        return $this->sendResponse($vendor_list, "Successful");
    }
}
