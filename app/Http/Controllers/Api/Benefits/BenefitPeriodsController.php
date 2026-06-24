<?php

namespace App\Http\Controllers\Api\Benefits;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Models\Benefit;

class BenefitPeriodsController extends BaseController
{
    public function show (Request $request, Benefit $benefit)
    {
        return $this->sendResponse($benefit->periods, "Successfully fetch benefit periods");
    }

    public function update (Request $request, Benefit $benefit)
    {
        $this->validate($request, [
            'id' => 'required',
            'effectivity_date' => 'required|date',
            'expiration_date' => 'required|date',
            'is_current' => 'required'
        ]);

        try {
            $data = $request->all();
            $benefit->periods()->where('id', $request->id)->update($data);

            return $this->sendResponse([], "Success");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), []);
        }
    }
}
