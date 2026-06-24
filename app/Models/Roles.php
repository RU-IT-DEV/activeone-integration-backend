<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AuditLogs;

class Roles extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'navigations'];

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
                'summary' => auth()->user()->name . " updated a record in Roles."
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
                'summary' => auth()->check() ? auth()->user()->name . " updated a record in Roles." : "System update on Roles.",
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
                'summary' => auth()->user()->name . " updated a record in Roles."
            ]);
        });
    }
}
