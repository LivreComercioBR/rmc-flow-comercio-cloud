<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use ScopesToCompany;

    protected $fillable = ['company_id', 'name', 'document', 'contact_name', 'email', 'phone', 'address', 'notes', 'status'];

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function payables(): HasMany
    {
        return $this->hasMany(AccountPayable::class);
    }
}
