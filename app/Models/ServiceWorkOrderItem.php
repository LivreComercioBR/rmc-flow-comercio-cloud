<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceWorkOrderItem extends Model
{
    use ScopesToCompany;

    protected $fillable = ['company_id', 'service_work_order_id', 'item_type', 'product_id', 'name', 'description', 'quantity', 'unit_price_cents', 'total_cents'];
    protected function casts(): array { return ['quantity' => 'decimal:3']; }
    public function workOrder(): BelongsTo { return $this->belongsTo(ServiceWorkOrder::class, 'service_work_order_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
