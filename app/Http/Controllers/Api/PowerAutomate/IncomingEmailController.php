<?php

namespace App\Http\Controllers\Api\PowerAutomate;

use App\Http\Controllers\Api\BaseController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class IncomingEmailController extends BaseController
{
    public function store(Request $request)
    {
        $data = $request->all();
        logger()->info("my Request from PA: ", $data);
        return $this->sendResponse([], "Success");
    }
}
