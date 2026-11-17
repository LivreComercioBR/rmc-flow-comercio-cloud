<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    use ScopesToCompany;

    protected $fillable = [
        'company_id', 'opening_amount_cents', 'expected_closing_cents', 'counted_closing_cents',
        'difference_cents', 'status', 'opened_at', 'opened_by', 'closed_at', 'closed_by', 'closing_note',
    ];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function movements(): HasMany { return $this->hasMany(CashMovement::class)->orderBy('created_at'); }
    public function opener(): BelongsTo { return $this->belongsTo(User::class, 'opened_by'); }
    public function closer(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }
}
