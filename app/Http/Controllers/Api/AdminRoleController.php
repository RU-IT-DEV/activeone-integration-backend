<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use App\Models\AdminNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class AdminRoleController extends Controller {
 private function authorizeAdmin(Request $request): void {
  abort_unless($request->user()?->role === 'admin', 403, 'Administrator access required.');
 }
 public function index(Request $request) {
  $this->authorizeAdmin($request);
  return response()->json(['data'=>AdminRole::with('permissions')->orderBy('name')->get()]);
 }
 public function navigations(Request $request) {
  $this->authorizeAdmin($request);
  return response()->json(['data'=>AdminNavigation::orderBy('sort_order')->orderBy('id')->get()]);
 }
 public function store(Request $request) {
  $this->authorizeAdmin($request);
  $data=$this->validated($request);
  $role=DB::transaction(function() use($data) {
   $role=AdminRole::create(collect($data)->only(['name','slug','description'])->all());
   $this->syncPermissions($role,$data['permissions'] ?? []);
   return $role;
  });
  return response()->json(['data'=>$role->load('permissions')],201);
 }
 public function update(Request $request, AdminRole $role) {
  $this->authorizeAdmin($request);
  $data=$this->validated($request,$role);
  DB::transaction(function() use($role,$data) {
   $role->update(collect($data)->only(['name','slug','description'])->all());
   $this->syncPermissions($role,$data['permissions'] ?? []);
  });
  return response()->json(['data'=>$role->refresh()->load('permissions')]);
 }
 public function destroy(Request $request, AdminRole $role) {
  $this->authorizeAdmin($request);
  $role->delete();
  return response()->json(['message'=>'Role deleted.']);
 }
 private function validated(Request $request, ?AdminRole $role=null): array {
  return $request->validate([
   'name'=>['required','string','max:100'],
   'slug'=>['required','string','max:100','regex:/^[a-z][a-z0-9_-]*$/',Rule::unique('admin_roles','slug')->ignore($role?->id)],
   'description'=>['nullable','string','max:1000'],
   'permissions'=>['required','array'],
   'permissions.*.admin_navigation_id'=>['required','integer','distinct','exists:admin_navigations,id'],
   'permissions.*.can_view'=>['required','boolean'],
   'permissions.*.can_create'=>['required','boolean'],
   'permissions.*.can_update'=>['required','boolean'],
   'permissions.*.can_delete'=>['required','boolean'],
  ]);
 }
 private function syncPermissions(AdminRole $role, array $permissions): void {
  $role->permissions()->delete();
  foreach($permissions as $permission) {
   if (!collect($permission)->only(['can_view','can_create','can_update','can_delete'])->contains(true)) continue;
   $role->permissions()->create($permission);
  }
 }
}
