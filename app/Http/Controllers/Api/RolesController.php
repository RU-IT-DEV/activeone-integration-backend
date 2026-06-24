<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Models\Roles;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class RolesController extends BaseController
{
    public function index() {
        $result = Roles::all();
        $message = "Roles successfully retrieved.";
        
        return $this->sendResponse(
            $result,
            $message
        );
    }

    public function store(Request $request) {
        $this->authorize('has-access', 'Roles-create');
        $this->validate($request, [
            'name' => 'required|string|unique:roles',
            'navigations' => 'required'
        ]);

        try {
            $data = $request->all();
            $data['navigations'] = json_encode($data['navigations'], true);
            $role = Roles::create($data);
            return $this->sendResponse($role, "Role successfully created.");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    public function update(Request $request, Roles $role) {
        $this->authorize('has-access', 'Roles-edit');
        $this->validate($request, [
            'name' => [
                'required','string',
                Rule::unique('roles', 'name')->ignore($role->id)
            ],
            'navigations' => 'required'
        ]);

        try {
            $data = $request->all();

            if (!$this->hasAccess(Auth::user(), 'Roles-edit.role_access')) {
                unset($data['navigations']);
            }

            $role->update($data);
            $role->save();

            return $this->sendResponse($role, "Role successfully updated.");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 404);
        }
    }

    public function destroy(Roles $role) {
        $this->authorize('has-access', 'Roles-delete');
        try {
            $role_name = $role->name;
            $role->delete();
            return $this->sendResponse(null, "A {$role_name} successfully deleted.");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }
}
