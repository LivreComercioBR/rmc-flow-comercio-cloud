<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    use ScopesToCompany;

    protected $fillable = ['company_id', 'purchase_id', 'product_id', 'quantity', 'unit_cost_cents', 'previous_cost_price_cents', 'total_cents'];

    protected function casts(): array { return ['quantity' => 'decimal:3']; }

    public function purchase(): BelongsTo { return $this->belongsTo(Purchase::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
