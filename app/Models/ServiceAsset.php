<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceAsset extends Model
{
    use ScopesToCompany;

    protected $fillable = ['company_id', 'customer_id', 'asset_type', 'name', 'manufacturer', 'model', 'year', 'color', 'identifier', 'notes', 'status'];
    protected function casts(): array { return ['year' => 'integer']; }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function workOrders(): HasMany { return $this->hasMany(ServiceWorkOrder::class, 'service_asset_id')->orderByDesc('opened_at'); }
}
