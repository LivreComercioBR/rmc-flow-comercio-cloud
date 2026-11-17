<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;

class ServiceCatalogItem extends Model
{
    use ScopesToCompany;

    protected $fillable = ['company_id', 'name', 'description', 'price_cents', 'status'];
}
