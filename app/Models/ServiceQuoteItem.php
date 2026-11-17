<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceQuoteItem extends Model
{
    use ScopesToCompany;

    protected $fillable = ['company_id', 'service_quote_id', 'item_type', 'service_catalog_item_id', 'product_id', 'name', 'description', 'quantity', 'unit_price_cents', 'total_cents'];
    protected function casts(): array { return ['quantity' => 'decimal:3']; }

    public function quote(): BelongsTo { return $this->belongsTo(ServiceQuote::class, 'service_quote_id'); }
    public function service(): BelongsTo { return $this->belongsTo(ServiceCatalogItem::class, 'service_catalog_item_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
