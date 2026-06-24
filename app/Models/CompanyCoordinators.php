<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AuditLogs;

class CompanyCoordinators extends Model
{
    use HasFactory;
    protected $fillable = [
        'company_id','position','name','email','contact_num',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

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
                'summary' => auth()->user()->name . " updated a record in Company coordinators.",
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
                'summary' => auth()->user()->name . " created a record in Company coordinators.",
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
                'summary' => auth()->user()->name . " deleted a record in Company coordinators.",
            ]);
        });
    }
}
