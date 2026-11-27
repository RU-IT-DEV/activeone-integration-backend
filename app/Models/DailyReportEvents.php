<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReportEvents extends Model
{
    protected $fillable = [
        'report_name',
        'file_path',
        'generated_at',
        'status',
        'mailable',
        'is_mail_sent',
    ];
}
