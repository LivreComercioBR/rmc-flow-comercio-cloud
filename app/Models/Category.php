<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use ScopesToCompany;

    protected $fillable = ['company_id', 'name', 'kind', 'status'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
