<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use ScopesToCompany;
    protected $fillable = ['company_id', 'sale_id', 'product_id', 'product_name', 'quantity', 'unit_price_cents', 'unit_cost_cents', 'line_total_cents'];
    protected function casts(): array { return ['quantity' => 'decimal:3']; }
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
