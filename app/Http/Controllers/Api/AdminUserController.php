<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
class AdminUserController extends Controller {
 private function admin(Request $request):void {abort_unless($request->user()?->role === 'admin',403,'Administrator access required.');}
 private function serialize(User $user):array {
  return ['id'=>$user->id,'name'=>$user->name,'email'=>$user->email,'position'=>$user->position,
   'role'=>$user->role,'admin_role_id'=>$user->admin_role_id,'admin_role'=>$user->adminRole,
   'status'=>$user->status,'created_at'=>$user->created_at];
 }
 public function index(Request $request) {
  $this->admin($request);
  $data=$request->validate(['search'=>['nullable','string','max:255'],'per_page'=>['nullable','integer','min:1','max:100']]);
  $query=User::query()->with('adminRole')->orderBy('name');
  if($term=trim($data['search']??'')) $query->where(function($q)use($term){$q->where('name','like',"%{$term}%")->orWhere('email','like',"%{$term}%");});
  $users=$query->paginate($data['per_page']??15);
  $users->getCollection()->transform(fn(User $user)=>$this->serialize($user));
  return response()->json(['data'=>$users,'roles'=>AdminRole::orderBy('name')->get()]);
 }
 public function store(Request $request) {
  $this->admin($request);
  $data=$this->validated($request);
  $user=User::create(array_merge($data,['password'=>Str::random(64),'role'=>'pharmacist']));
  return response()->json(['data'=>$this->serialize($user->load('adminRole'))],201);
 }
 public function update(Request $request,User $user) {
  $this->admin($request);
  $data=$this->validated($request,$user);
  $user->update($data);
  return response()->json(['data'=>$this->serialize($user->load('adminRole'))]);
 }
 public function destroy(Request $request,User $user) {
  $this->admin($request);
  abort_if($user->id===$request->user()->id,422,'You cannot delete your own account.');
  $user->tokens()->delete();
  $user->delete();
  return response()->json(['message'=>'User deactivated and access tokens revoked.']);
 }
 private function validated(Request $request,?User $user=null):array {
  return $request->validate([
   'name'=>['required','string','max:255'],
   'email'=>['required','email','max:255',Rule::unique('users','email')->ignore($user?->id)],
   'position'=>['nullable','string','max:255'],
   'admin_role_id'=>['nullable','integer','exists:admin_roles,id'],
  ]);
 }
}