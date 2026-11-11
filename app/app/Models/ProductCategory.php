<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'min_age'];

    protected $casts = [
        'min_age' => 'integer',
    ];

    public function isAgeRestricted(): bool
    {
        return !is_null($this->min_age) && $this->min_age > 0;
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
