<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use App\Models\AdminNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class AdminRoleController extends Controller {
 private function admin(Request $request):void {abort_unless($request->user()?->role==='admin',403,'Administrator access required.');}
 public function index(Request $request) {
  $this->admin($request);
  return response()->json(['data'=>AdminRole::with('permissions')->orderBy('name')->get(),'navigations'=>AdminNavigation::orderBy('sort_order')->get()]);
 }
 public function store(Request $request) {
  $this->admin($request);$data=$this->validated($request);
  $role=DB::transaction(function()use($data){$role=AdminRole::create(['name'=>$data['name'],'description'=>$data['description']??null]);$this->sync($role,$data['permissions']??[]);return $role;});
  return response()->json(['data'=>$role->load('permissions')],201);
 }
 public function update(Request $request,AdminRole $role) {
  $this->admin($request);$data=$this->validated($request,$role);
  DB::transaction(function()use($role,$data){$role->update(['name'=>$data['name'],'description'=>$data['description']??null]);$this->sync($role,$data['permissions']??[]);});
  return response()->json(['data'=>$role->load('permissions')]);
 }
 public function destroy(Request $request,AdminRole $role) {
  $this->admin($request);$role->delete();return response()->json(['message'=>'Role deleted.']);
 }
 private function validated(Request $request,?AdminRole $role=null):array {
  return $request->validate([
   'name'=>['required','string','max:100',Rule::unique('admin_roles','name')->ignore($role?->id)],
   'description'=>['nullable','string','max:2000'],
   'permissions'=>['array'],
   'permissions.*.admin_navigation_id'=>['required','integer','distinct','exists:admin_navigations,id'],
   'permissions.*.can_view'=>['required','boolean'],
   'permissions.*.can_create'=>['required','boolean'],
   'permissions.*.can_update'=>['required','boolean'],
   'permissions.*.can_delete'=>['required','boolean']
  ]);
 }
 private function sync(AdminRole $role,array $permissions):void {
  $role->permissions()->delete();
  foreach($permissions as $p){
   if(!$p['can_view'] && ($p['can_create']||$p['can_update']||$p['can_delete'])) abort(422,'View permission is required for other actions.');
   $role->permissions()->create($p);
  }
 }
}