<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Auth;
use App\Models\BenefitCategoryOptions;

class BenefitCategoryOptionsController extends BaseController
{
    //
    public function index() {
        $query = BenefitCategoryOptions::query();
        $categories = $query->get();
        return $this->sendResponse(($categories), 'Categories fetched successfully.');

    }

    public function retrieveByCategory ($type) {

        $query = BenefitCategoryOptions::query();
        $query->where('type', $type);
        $categories = $query->get();
        return $this->sendResponse($categories, $type.' Categories fetched successfully.');

    }
}
