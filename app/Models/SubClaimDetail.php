<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubClaimDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_claim_id',
        'expense_type',
        'category',
        'sub_category',
        'purpose',
        'parking_location',
        'vehicle_plate_number',
        'activities_or_items',
        'description',
        'beneficiary',
        'relation_to_employee',
        'vendor_name',
        'receipt_date',
        'vendor_tin',
        'vendor_address',
        'or_number',
        'amount',
        'receipt',
        'approved_amount',
        'rejected_amount',
        'rejection_reason'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'rejected_amount' => 'decimal:2',
    ];

    public function memberClaim()
    {
        return $this->belongsTo(MemberClaim::class, 'member_claim_id', 'id');
    }

    public function attachments()
    {
        return $this->hasMany(SubClaimDetailAttachments::class);
    }
}
