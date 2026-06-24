<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ClaimCategory extends Model
{
    use HasFactory;

    protected $fillable = ['claim_type', 'name'];

    public function subcategories(): HasMany
    {
        return $this->hasMany(ClaimSubcategory::class, 'category_id');
    }
}

