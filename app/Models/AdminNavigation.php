<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AdminNavigation extends Model {
    protected $fillable = ['title', 'route', 'icon', 'sort_order', 'is_active','available_actions'];
    protected $casts = ['sort_order' => 'integer', 'is_active' => 'boolean','available_actions' => 'array'];
}
