<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use ScopesToCompany;

    protected $fillable = ['company_id', 'customer_id', 'sale_number', 'status', 'subtotal_cents', 'discount_cents', 'total_cents', 'paid_cents', 'balance_cents', 'request_key', 'sold_at', 'created_by', 'cancelled_at', 'cancelled_by', 'cancelled_approved_by', 'cancellation_reason'];
    protected function casts(): array { return ['sold_at' => 'datetime', 'cancelled_at' => 'datetime']; }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function items(): HasMany { return $this->hasMany(SaleItem::class); }
    public function payments(): HasMany { return $this->hasMany(SalePayment::class); }
    public function receivables(): HasMany { return $this->hasMany(AccountReceivable::class); }
    public function refunds(): HasMany { return $this->hasMany(SaleRefund::class); }
    public function cancelledBy(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function cancellationApprover(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_approved_by'); }
}
