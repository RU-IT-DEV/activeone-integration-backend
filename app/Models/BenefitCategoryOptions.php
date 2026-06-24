<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BenefitCategoryOptions extends Model
{
    use HasFactory;
    protected $fillable = ['benefit_id', 'name', 'type','allow_dependent'];

    // Relationship to Benefit
    public function benefit()
    {
        return $this->belongsTo(Benefit::class);
    }
}
