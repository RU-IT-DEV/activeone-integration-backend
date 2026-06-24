<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Navigations;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class NavigationsController extends BaseController
{
    public function index() {
        $result = Navigations::with(['mainNavigationData' => function ($query) {
            $query->select('name', 'id');
        }])->get();
        $message = "Navigations successfully retrieved.";
        
        $this->saveViewLog('view', 'Navigations');
        return $this->sendResponse(
            $result,
            $message
        );
    }

    public function store(Request $request) {
        $this->authorize('has-access', 'Navigations-create');
        // validate store
        $this->validate($request, [
            'icon' => 'nullable|string',
            'name' => 'required|string|unique:navigations',
            'main_navigation' => 'nullable|exists:navigations',
            'href' => 'required|regex:/^\/(?:[a-z]+(?:\/[a-z]+)*)?$/',
            'actions' => 'required|array'
        ]);

        try {
            $data = $request->all();
            $data['actions'] = json_encode($data['actions']);
            $navigation = Navigations::create($data);
            return $this->sendResponse($navigation, "Navigation successfully created.");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    public function update(Request $request, Navigations $navigation) {
        $this->authorize('has-access', 'Navigations-edit');
        // validate store
        $this->validate($request, [
            'icon' => 'nullable|string',
            'name' => [
                'required','string',
                Rule::unique('navigations', 'name')->ignore($navigation->id)
            ],
            'main_navigation' => 'nullable|exists:navigations,id',
            'href' => 'required|regex:/^\/(?:[a-z]+(?:\/[a-z]+)*)?$/',
            'actions' => 'required|array'
        ]);

        try {
            $data = $request->all();
            if (!$this->hasAccess(Auth::user(), 'Navigations-edit.href')) unset($data['href']);
            if (!$this->hasAccess(Auth::user(), 'Navigations-edit.actions')) unset($data['actions']);
            
            $navigation->update($data);
            $navigation->save();

            return $this->sendResponse($navigation, "Navigation successfully updated.");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    public function destroy(Navigations $navigation) {
        $this->authorize('has-access', 'Navigations-delete');
        try {
            $navigation->delete();
            return $this->sendResponse(null, "Navigation successfully deleted.");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    public function toggleStatus(Request $request, Navigations $navigation) {
        $this->authorize('has-access', 'Navigations-edit.status');
        try {
            $navigation->status = !$navigation->status;
            $navigation->save();
            return $this->sendResponse($navigation, "Navigation status successfully updated.");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }
}
