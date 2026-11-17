<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountPayable extends Model
{
    use ScopesToCompany;

    protected $fillable = [
        'company_id', 'purchase_id', 'supplier_id', 'financial_category_id', 'manual_request_key', 'description', 'installment_number', 'due_date',
        'amount_cents', 'paid_cents', 'cancelled_balance_cents', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function purchase(): BelongsTo { return $this->belongsTo(Purchase::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class, 'financial_category_id'); }
    public function payments(): HasMany { return $this->hasMany(AccountPayablePayment::class)->orderBy('paid_at'); }

    public function remainingCents(): int
    {
        return max(0, (int) $this->amount_cents - (int) $this->paid_cents - (int) $this->cancelled_balance_cents);
    }
}
