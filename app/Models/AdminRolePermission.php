<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AdminRolePermission extends Model {
 public $timestamps = false;
 protected $fillable = ['admin_role_id','admin_navigation_id','can_view','can_create','can_update','can_delete'];
 protected $casts = ['can_view'=>'boolean','can_create'=>'boolean','can_update'=>'boolean','can_delete'=>'boolean'];
 public function navigation() { return $this->belongsTo(AdminNavigation::class, 'admin_navigation_id'); }
}
