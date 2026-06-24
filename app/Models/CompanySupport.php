<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySupport extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'label'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'company_id'
    ];

    public function company ()
    {
        return $this->belongsTo(Company::class);
    }
}
