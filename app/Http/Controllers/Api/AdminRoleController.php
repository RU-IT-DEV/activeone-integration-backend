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
  return response()->json(['data'=>AdminRole::with('permissions')->orderBy('name')->get(),'navigations'=>AdminNavigation::orderBy('sort_order')->get()->map(function($navigation){$navigation->available_actions=config('admin_navigation_actions.'.$navigation->route, ['can_view']);return $navigation;})]);
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
   'permissions.*.actions'=>['required','array'],
   'permissions.*.actions.*'=>['string','regex:/^can_[a-z_]+$/']
  ]);
 }
 private function sync(AdminRole $role,array $permissions):void {
  $navs=AdminNavigation::whereIn('id',array_column($permissions,'admin_navigation_id'))->get()->keyBy('id');
  $prepared=[];
  foreach($permissions as $p){
   $nav=$navs->get($p['admin_navigation_id']);
   $allowed=config('admin_navigation_actions.'.$nav->route,['can_view']);
   $actions=array_values(array_unique($p['actions']));
   if(array_diff($actions,$allowed)) abort(422,'Unsupported navigation action.');
   if(count($actions)>0 && !in_array('can_view',$actions,true)) abort(422,'View permission is required for other actions.');
   $prepared[]=['admin_navigation_id'=>$nav->id,'actions'=>$actions,'can_view'=>in_array('can_view',$actions,true),
    'can_create'=>in_array('can_create',$actions,true),'can_update'=>in_array('can_update',$actions,true),'can_delete'=>in_array('can_delete',$actions,true)];
  }
  $role->permissions()->delete();
  foreach($prepared as $p) $role->permissions()->create($p);
 }
}