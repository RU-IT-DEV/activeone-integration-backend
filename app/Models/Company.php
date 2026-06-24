<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\AuditLogs;

class Company extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'client_id','name','code'
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
                'summary' => auth()->user()->name . " updated a record in Company.",
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
                'summary' => auth()->user()->name . " created a record in Company.",
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
                'summary' => auth()->user()->name . " deleted a record in Company.",
            ]);
        });
    }
      // Defining the relationship

    public function coordinators()
    {
        return $this->hasMany(CompanyCoordinators::class);
    }

    public function documents()
    {
        return $this->hasMany(CompanyDocuments::class);
    }
     public function companyStatus()
     {
         return $this->hasMany(CompanyStatus::class);
     }

    public function companyContractPeriod()
    {
        return $this->hasMany(CompanyContractPeriod::class);
    }
    public function benefit()
    {
        return $this->hasMany(Benefit::class);
    }
    public function member()
    {
        return $this->hasMany(Members::class, 'company_code');
    }
    public function members()
    {
        return $this->hasMany(Members::class, 'company_code', 'code');
    }

    public function userAccesses()
    {
        return $this->hasMany(UserCompanyAccess::class);
    }

    public function support()
    {
        return $this->hasMany(CompanySupport::class);
    }

}


