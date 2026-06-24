<?php

namespace App\Http\Controllers\Api;

use App\Models\Company;
use App\Models\CompanyCoordinators;
use Illuminate\Http\Request;

class CompanyCoordinatorsController extends BaseController
{
    public function store (Request $request, Company $company) {
        $this->authorize('has-access', 'Companies-edit.account_poc');
        $this->validate($request, [
            'company_coordinators' => 'array',
            'company_coordinators.*.name' => 'required|string',
            'company_coordinators.*.email' => 'required|email',
            'company_coordinators.*.position' => 'string',
            'company_coordinators.*.contact_num' => 'numeric'
        ]);

        try {
            $coordinators = $request->input('coordinators');
            $arr_coordinators = array_values($coordinators);
            foreach ($arr_coordinators as $coordinator) {
                $company->coordinators()->create([
                    'position' => $coordinator['position'],
                    'name' => $coordinator['name'],
                    'email' => $coordinator['email'],
                    'contact_num' => $coordinator['contact_num'],
                ]);    
            }

            $result = $company->coordinators;
            return $this->sendResponse($result, "Successfully added coordinator(s).");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), $e->errors(), 400);
        }
    }

    public function update (Request $request, Company $company, $id) {
        try {
            $this->validate($request, [
                'name' => 'required|string',
                'email' => 'required|email',
                'position' => 'nullable|string',
                'contact_num' => 'nullable|numeric'
            ]);
            $coordinator = CompanyCoordinators::find($id);
            $data = $request->all();
            $coordinator->update($data);

            $result = $company->coordinators;
            return $this->sendResponse($result, "Successfully updated a coordinator.");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), $e->errors(), 400);
        }
    }

    public function destroy (Company $company, $id) {
        try {
            $coordinator = CompanyCoordinators::find($id);
            $name = $coordinator->name;
            $coordinator->delete();

            $result = $company->coordinators;
            return $this->sendResponse($result, "$name has been deleted.");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }
}
