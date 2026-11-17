<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    use ScopesToCompany;

    protected $fillable = [
        'company_id', 'supplier_id', 'invoice_number', 'purchased_at', 'total_cents',
        'installment_count', 'status', 'request_key', 'notes', 'created_by', 'cancelled_at',
        'cancelled_by', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return ['purchased_at' => 'date', 'cancelled_at' => 'datetime'];
    }

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function items(): HasMany { return $this->hasMany(PurchaseItem::class); }
    public function payables(): HasMany { return $this->hasMany(AccountPayable::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
