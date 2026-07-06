<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    public function register(Request $request) {
        $this->validate($request, [
            'hmoNumber' => 'required|string|max:16'
        ]);

        try {
            $data = $request->all();
            logger()->info($data);

            return $this->sendResponse([], "Success.");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), "Something went wrong. Call your administrator", 400);
        }
    }
}
