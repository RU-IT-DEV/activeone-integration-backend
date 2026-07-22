<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;

class CartController extends BaseController
{
    public function store(Request $request)
    {
        $data = $request->all();

        logger()->info("show data: ", $data);

        return $this->sendResponse([
            'id' => 1,
            'token' => $request->token,
        ], "Success");
    }
}
