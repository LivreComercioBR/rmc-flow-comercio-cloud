<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use ScopesToCompany;

    protected $fillable = [
        'company_id', 'cash_session_id', 'direction', 'movement_type', 'amount_cents',
        'description', 'source_type', 'source_id', 'request_key', 'created_by',
    ];

    public function session(): BelongsTo { return $this->belongsTo(CashSession::class, 'cash_session_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
