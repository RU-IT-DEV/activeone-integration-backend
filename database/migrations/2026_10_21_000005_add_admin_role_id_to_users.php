<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
return new class extends Migration {
 public function up():void {
  // Do not coerce legacy text role values to integers. Require an explicit mapping first.
  $values=DB::table('users')->select('role')->distinct()->pluck('role');
  $legacy=$values->filter(fn($value)=>$value!==null && !ctype_digit((string)$value))->values();
  if($legacy->isNotEmpty()) throw new RuntimeException(
   'Map existing users.role values to admin_roles IDs before migrating: '.$legacy->implode(', ')
  );
  $ids=$values->filter(fn($value)=>$value!==null)->map(fn($value)=>(int)$value)->all();
  if($ids && DB::table('admin_roles')->whereIn('id',$ids)->count()!==count(array_unique($ids)))
   throw new RuntimeException('Some users.role IDs do not exist in admin_roles.');
  Schema::table('users',function(Blueprint $table){$table->unsignedBigInteger('role')->nullable()->change();});
  Schema::table('users',function(Blueprint $table){$table->foreign('role','users_role_admin_roles_fk')->references('id')->on('admin_roles')->restrictOnDelete();});
 }
 public function down():void {
  Schema::table('users',function(Blueprint $table){$table->dropForeign('users_role_admin_roles_fk');});
  Schema::table('users',function(Blueprint $table){$table->string('role')->nullable()->change();});
 }
};