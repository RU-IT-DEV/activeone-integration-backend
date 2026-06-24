<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberPlanBucket extends Model
{
    use HasFactory;
    protected $fillable = [
        'member_plan_link_id',
        'coverage_type',
        'allocated_limit',
        'used_limit',
        'remaining_limit',
    ];

    public function planLink()
    {
        return $this->belongsTo(MemberPlanLink::class);
    }

    public static function insertBucketData($memberPlanLinkId, $coverageType, $allocatedLimit, $usedLimit, $remainingLimit)
    {
        // Validate input parameters (optional)
        // Perform any additional logic here if necessary

        // Insert data into the table
        return self::create([
            'member_plan_link_id' => $memberPlanLinkId,
            'coverage_type' => $coverageType,
            'allocated_limit' => $allocatedLimit,
            'used_limit' => $usedLimit,
            'remaining_limit' => $remainingLimit,
        ]);
    }

    public function responses()
    {
        return $this->hasMany(ClaimsResponse::class, 'member_plan_bucket_id', 'id');
    }
}
