<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberEmailVerification extends Model
{
    use HasFactory;

    protected $table = 'member_email_verification';

    protected $fillable = [
        'member_id',
        'token',
        'sent_date',
        'status',
    ];

    const CREATED_AT = 'sent_date';
    const UPDATED_AT = 'sent_date';

    public function member () {
        return $this->belongsTo(Members::class);
    }
}
