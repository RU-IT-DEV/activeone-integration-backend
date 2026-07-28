<?php

namespace App\Http\Controllers\Api;

use App\Helper\IntellicareHelper;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;

class DoctorsController extends BaseController
{
    public function index(Request $request, IntellicareHelper $intellicareHelper)
    {
        try {
            $reqData = [
                'prccode' => $request->prccode
            ];
            $data = $intellicareHelper->getDoctors($reqData);

            return $this->sendResponse($data, "Success.");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), []);
        }
    }
}
