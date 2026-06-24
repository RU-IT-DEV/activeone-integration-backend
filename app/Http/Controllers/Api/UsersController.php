<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Roles;
use App\Models\UserCompanyAccess;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Validator;

class UsersController extends BaseController
{
    //
    public function index() {

        $result = User::with([
            'role:name,id',
            'companyAccesses.company:id,name' // Eager load company name if needed
        ])->get();
        
        $message = "Users successfully retrieved.";
        
        return $this->sendResponse(
            $result,
            $message
        );
    }
    public function show($user_id)
    {
        // $result = User::with('role:name,id,navigations,companyAccesses')
        //     ->where('id',$user_id)
        //     ->first();

        $result = User::with([
            'role:id,name,navigations',
            'companyAccesses' => function ($query) {
                $query->with('company:id,name'); // assuming relation to Company
            }
        ])->find($user_id);
    
        if (!$result) {
            return $this->sendError('User not found.', [], 404);
        }
        
        if ($result && $result->role && is_string($result->role->navigations)) {
            $result->role->navigations = json_decode($result->role->navigations, true);
        }
        $message = "Users successfully retrieved.";
        
        return $this->sendResponse(
            $result,
            $message
        );

    }
    public function store(Request $request) {
        $this->authorize('has-access', 'Users-create');

        $input_request = $request->all();
        $validator = Validator::make($input_request, [
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'role_id' => 'required',
            'company_accesses' => 'nullable|array',
            'company_accesses.*.company_id' => 'required|integer|exists:companies,id',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validator Error.', $validator->errors());
        }
        try {
            $user = User::create($input_request);
             // Optional: clear and re-assign company accesses
             if (isset($input_request['company_accesses'])) {

                foreach ($input_request['company_accesses'] as $access) {
                    $user->companyAccesses()->create([
                        'company_id' => $access['company_id'],
                    ]);
                }
            }
            
            return $this->sendResponse($user, "User successfully created.");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    public function update(Request $request, User $user) {
        $this->authorize('has-access', 'Users-edit');

        $input_request = $request->all();
        $userId = $request->route('user'); 

        $validator = Validator::make($input_request, [
            'name' => 'required|string',
            // 'email' => 'required|email|unique:users,email',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'role_id' => 'required',
            'status' => 'required',
            'company_accesses' => 'nullable|array',
            'company_accesses.*.company_id' => 'required|integer|exists:companies,id',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validator Error.', $validator->errors());
        }
        try {
            $user->update($input_request);

            // Optional: clear and re-assign company accesses
            if (isset($input_request['company_accesses'])) {
                // Delete existing access records (or use sync logic)
                $user->companyAccesses()->delete();

                foreach ($input_request['company_accesses'] as $access) {
                    $user->companyAccesses()->create([
                        'company_id' => $access['company_id'],
                    ]);
                }
            }

            return $this->sendResponse($user->load('companyAccesses'), "User successfully updated.");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    public function destroy(User $user) {
        $this->authorize('has-access', 'Users-delete');
        
        $user->delete();

        return $this->sendResponse([
            "name" => Auth::user()->email,
        ], "You've deleted user successfully.");
    }

    public function getCount () {

         // Count Active 
         $rolesCount = Roles::count();
         $activeCount = User::count();
         $deactivatedCount = User::where('status','deactivated')->count();
         $softDeletedCount = User::onlyTrashed()->count();

        // Return the response as JSON
        $data = [
            'roles' => $rolesCount,
            'active' => $activeCount,
            'deactivated' => $deactivatedCount,
            'delete' => $softDeletedCount,
        ];
        return $this->sendResponse($data, "Users dashboard count retrieved successfully.");
    }

}
