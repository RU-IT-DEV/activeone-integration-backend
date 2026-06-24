<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubClaimDetailAttachments extends Model
{
    use HasFactory;

    protected $table = "sub_claim_details_attachments";

    protected $fillable = ['filepath'];

    public function subClaimDetail ()
    {
        return $this->belongsTo(SubClaimDetail::class);
    }
}
