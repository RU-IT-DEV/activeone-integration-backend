<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AuditLogs;

class CompanyContractPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','contract_period_start','contract_period_end','account_officer','is_current','isNotified'
    ];

    protected static function boot ()
    {
        parent::boot();

        static::updated(function ($model) {
            AuditLogs::create([
                'user_id' => auth()->id(),
                'event' => 'updated',
                'auditable_type' => self::class,
                'auditable_id' => $model->id,
                'old_values' => $model->getOriginal(),
                'new_values' => $model->getAttributes(),
                'severity' => 0,
                'summary' => auth()->check() ? auth()->user()->name . "updated a record in Company contract periods." : "System updated a record in Company contract periods."
            ]);
        });

        static::created(function ($model) {
            AuditLogs::create([
                'user_id' => auth()->id(),
                'event' => 'created',
                'auditable_type' => self::class,
                'auditable_id' => $model->id,
                'old_values' => $model->getOriginal(),
                'new_values' => $model->getAttributes(),
                'severity' => 0,
                'summary' => auth()->user()->name . " created a record in Company contract periods.",
            ]);
        });

        static::deleted(function ($model) {
            AuditLogs::create([
                'user_id' => auth()->id(),
                'event' => 'deleted',
                'auditable_type' => self::class,
                'auditable_id' => $model->id,
                'old_values' => $model->getOriginal(),
                'new_values' => $model->getAttributes(),
                'severity' => 0,
                'summary' => auth()->user()->name . " deleted a record in Company contract periods.",
            ]);
        });
    }

     // Define the inverse of the relationship
     public function company()
     {
         return $this->belongsTo(Company::class);
     }
}
