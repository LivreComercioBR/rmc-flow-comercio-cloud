<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountPayablePayment extends Model
{
    use ScopesToCompany;

    protected $fillable = ['company_id', 'account_payable_id', 'amount_cents', 'paid_at', 'payment_method', 'cash_session_id', 'request_key', 'reference', 'notes', 'created_by'];

    protected function casts(): array { return ['paid_at' => 'datetime']; }

    public function payable(): BelongsTo { return $this->belongsTo(AccountPayable::class, 'account_payable_id'); }
    public function cashSession(): BelongsTo { return $this->belongsTo(CashSession::class, 'cash_session_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
