<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use ScopesToCompany;

    protected $fillable = [
        'company_id', 'category_id', 'name', 'sku', 'barcode', 'unit',
        'cost_price_cents', 'sale_price_cents', 'icms_rate', 'ipi_rate', 'import_tax_rate', 'export_tax_rate',
        'iss_rate', 'operational_cost_rate', 'profit_rate', 'other_costs_cents', 'stock_quantity', 'minimum_stock', 'shelf', 'status',
    ];

    protected function casts(): array
    {
        return ['stock_quantity' => 'decimal:3', 'minimum_stock' => 'decimal:3', 'icms_rate' => 'decimal:4', 'ipi_rate' => 'decimal:4', 'import_tax_rate' => 'decimal:4', 'export_tax_rate' => 'decimal:4', 'iss_rate' => 'decimal:4', 'operational_cost_rate' => 'decimal:4', 'profit_rate' => 'decimal:4'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest();
    }
}
