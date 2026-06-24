<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AuditLogs;

class CompanyDocuments extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','name','document_type','file_path','filename','status'
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
                'summary' => auth()->user()->name . " updated a document for Company #{$model->id} documents.",
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
                'summary' => auth()->user()->name . " added a document for Company #{$model->id}.",
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
                'summary' => auth()->user()->name . " deleted a document for Company #{$model->id}.",
            ]);
        });
    }
}
