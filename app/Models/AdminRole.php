<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AdminRole extends Model {
 protected $fillable = ['name','slug','description'];
 public function permissions() { return $this->hasMany(AdminRolePermission::class); }
}
