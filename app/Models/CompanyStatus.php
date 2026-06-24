<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AuditLogs;

class CompanyStatus extends Model
{
    use HasFactory;
    protected $fillable = [
        'company_id','status','contract_status','effectivity_date','is_current','reason','created_by','is_executed', 'contract_id'
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
                'summary' => auth()->check() ? auth()->user()->name . "updated Company #{$model->id} status." : "System updated Company #{$model->id} status."
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
                'summary' => auth()->user()->name . " set Company #{$model->id} status.",
            ]);
        });
    }

     // Define the inverse of the relationship
     public function company()
     {
         return $this->belongsTo(Company::class);
     }
}
