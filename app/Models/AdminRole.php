<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AdminRole extends Model {
 protected $fillable=['name','description'];
 public function permissions(){return $this->hasMany(AdminRoleNavigationPermission::class);}
}