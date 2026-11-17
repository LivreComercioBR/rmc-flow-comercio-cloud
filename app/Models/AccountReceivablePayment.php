<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountReceivablePayment extends Model
{
    use ScopesToCompany;
    protected $fillable = ['company_id', 'account_receivable_id', 'payment_method', 'amount_cents', 'cash_session_id', 'reference', 'request_key', 'paid_at', 'created_by'];
    protected function casts(): array { return ['paid_at' => 'datetime']; }
    public function receivable(): BelongsTo { return $this->belongsTo(AccountReceivable::class, 'account_receivable_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function cashSession(): BelongsTo { return $this->belongsTo(CashSession::class, 'cash_session_id'); }
}
