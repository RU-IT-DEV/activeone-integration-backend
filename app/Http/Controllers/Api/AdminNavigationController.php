<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AdminNavigation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminNavigationController extends Controller {
    private function authorizeAdmin(Request $request): void {
        abort_unless($request->user() && $request->user()->role === 'admin', 403, 'Administrator access required.');
    }
    public function index(Request $request) {
        $this->authorizeAdmin($request);
        return response()->json(['data' => AdminNavigation::orderBy('sort_order')->orderBy('id')->get()]);
    }
    public function store(Request $request) {
        $this->authorizeAdmin($request);
        $data = $this->validateNavigation($request);
        if (!in_array('can_view', $data['available_actions'], true)) abort(422, 'Every navigation must define can_view.');
        return response()->json(['data' => AdminNavigation::create($data)], 201);
    }
    public function update(Request $request, AdminNavigation $navigation) {
        $this->authorizeAdmin($request);
        $data = $this->validateNavigation($request, $navigation);
        if (!in_array('can_view', $data['available_actions'], true)) abort(422, 'Every navigation must define can_view.');
        $navigation->update($data);
        return response()->json(['data' => $navigation->refresh()]);
    }
    public function destroy(Request $request, AdminNavigation $navigation) {
        $this->authorizeAdmin($request);
        $navigation->delete();
        return response()->json(['message' => 'Navigation deleted.']);
    }
    private function validateNavigation(Request $request, ?AdminNavigation $navigation = null): array {
        return $request->validate([
            'title' => ['required','string','max:100'],
            'route' => ['required','string','max:255','starts_with:/activeone/','regex:/^\\/activeone\\/[a-z0-9\\/-]+$/',Rule::unique('admin_navigations','route')->ignore($navigation?->id)],
            'icon' => ['required','string','max:100','regex:/^mdi-[a-z0-9-]+$/'],
            'sort_order' => ['required','integer','min:0'],
            'is_active' => ['required','boolean'],
            'available_actions' => ['required','array','min:1','max:40'],
            'available_actions.*' => ['required','string','distinct','regex:/^can_[a-z][a-z0-9_]*$/','max:80']
        ]);
    }
}
