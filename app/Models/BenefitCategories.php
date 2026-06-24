<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BenefitCategories extends Model
{
    use HasFactory;
    protected $fillable = ['benefit_id', 'name', 'amount'];

    // Relationship to Benefit
    public function benefit()
    {
        return $this->belongsTo(Benefit::class);
    }

    public function sub_categories()
    {
        return $this->hasManyThrough(ClaimSubcategory::class, ClaimCategory::class);
    }
}
