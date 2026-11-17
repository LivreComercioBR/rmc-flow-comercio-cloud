<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountReceivable extends Model
{
    use ScopesToCompany;
    protected $table = 'accounts_receivable';
    protected $fillable = ['company_id', 'sale_id', 'customer_id', 'financial_category_id', 'manual_request_key', 'service_work_order_id', 'description', 'installment_number', 'amount_cents', 'paid_cents', 'cancelled_balance_cents', 'due_date', 'status'];
    protected function casts(): array { return ['due_date' => 'date']; }
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function workOrder(): BelongsTo { return $this->belongsTo(ServiceWorkOrder::class, 'service_work_order_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class, 'financial_category_id'); }
    public function payments(): HasMany { return $this->hasMany(AccountReceivablePayment::class, 'account_receivable_id')->orderBy('paid_at'); }
    public function remainingCents(): int { return max(0, (int) $this->amount_cents - (int) $this->paid_cents - (int) $this->cancelled_balance_cents); }
}
