<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberBankDetail extends Model
{
    use HasFactory;

    protected $table = 'member_bank_details';

    protected $fillable = [
        'member_id',
        'bank_name',
        'account_name',
        'account_number',
        'branch',
        'account_type',
    ];

    public function member()
    {
        return $this->belongsTo(Members::class);
    }
}
