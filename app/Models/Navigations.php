<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AuditLogs;

class Navigations extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'icon', 'href', 'main_navigation', 'actions'];

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
                'status' => 'success',
                'summary' => auth()->user()->name . " updated a record in Navigations.",
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
                'status' => 'success',
                'summary' => auth()->check() ? auth()->user()->name . " updated a record in Navigations." : "System update on Navigations.",
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
                'status' => 'success',
                'summary' => auth()->user()->name . " deleted a record in Navigations.",
            ]);
        });
    }

    public function mainNavigationData()
    {
        return $this->belongsTo(Navigations::class, 'main_navigation');
    }
}
