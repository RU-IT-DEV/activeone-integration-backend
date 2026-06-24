<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AuditLogs;

class MemberClaims extends Model
{
    use HasFactory;

    protected $guard = ['member_id', 'member_plan_links_id', 'claim_id'];

    protected $fillable = [
        'vendor_name',
        'vendor_address',
        'tin_number',
        'coverage',
        'category',
        'total_amount',
        'service_date',
        'receipt',
        'status',
        'member_id',
        'member_plan_links_id',
        'claim_id',
        'type',
        'version',
        'freshdesk_claim_id',
        'received_date'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2'
    ];

    public function logAudit($event, $oldSubClaims = [])
    {
        $oldValues = self::removeNullValues($this->getOriginal());
        $newValues = self::removeNullValues($this->getAttributes());

        // Attach sub-claims (old and new values)
        $oldValues['sub_claims'] = self::removeNullValues($oldSubClaims);
        $newValues['sub_claims'] = $this->subClaimDetails()->get()->map(function ($subClaim) {
            return self::removeNullValues($subClaim->getAttributes());
        })->toArray();

        AuditLogs::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => self::class,
            'auditable_id' => $this->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'severity' => 0,
            'summary' => auth()->user()->name . " {$event} a record in Claim.",
        ]);
    }
    public static function removeNullValues(array $data)
    {
        return array_filter($data, function ($value) {
            return !is_null($value);
        });
    }

    public function claim_logs () {
        return $this->hasMany(MemberClaimsLogs::class, 'claim_id', 'id');
    }

    public function response()
    {
        return $this->hasOne(ClaimsResponse::class, 'member_claim_id', 'id');
    }

    public function member()
    {
        return $this->belongsTo(Members::class, 'member_id', 'id');
    }

    public function subClaimDetails()
    {
        return $this->hasMany(SubClaimDetail::class, 'member_claim_id');
    }
    
    public function attachments()
    {
        return $this->hasMany(MemberClaimsAttachments::class, 'member_claim_id');
    }

    public function planLink()
    {
        return $this->belongsTo(MemberPlanLink::class, 'member_plan_links_id', 'id');
    }

    public function qrScanTracking()
    {
        return $this->hasOne(ClaimQrScanLog::class, 'member_claim_id', 'id');
    }
}
