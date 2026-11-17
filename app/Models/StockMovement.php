<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use ScopesToCompany;

    protected $fillable = ['company_id', 'product_id', 'movement_type', 'quantity_delta', 'balance_after', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['quantity_delta' => 'decimal:3', 'balance_after' => 'decimal:3'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
