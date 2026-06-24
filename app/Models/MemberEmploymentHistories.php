<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberEmploymentHistories extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id', 'hire_date', 'leave_date', 'salary_grd', 'remarks'
    ];

    public function member()
    {
        return $this->belongsTo(Members::class, 'member_id');
    }
}
