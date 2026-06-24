<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLogs extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array'
    ];

    public function user() {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}
