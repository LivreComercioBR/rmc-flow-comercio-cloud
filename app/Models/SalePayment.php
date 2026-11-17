<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    use ScopesToCompany;
    protected $fillable = ['company_id', 'sale_id', 'payment_method', 'amount_cents', 'reference', 'request_key', 'paid_at'];
    protected function casts(): array { return ['paid_at' => 'datetime']; }
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
}
