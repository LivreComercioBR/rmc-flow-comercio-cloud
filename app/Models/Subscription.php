<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = ['company_id', 'plan_id', 'provider', 'provider_reference', 'status', 'current_period_start', 'current_period_end', 'cancelled_at', 'metadata'];
    protected function casts(): array
    {
        return ['current_period_start' => 'datetime', 'current_period_end' => 'datetime', 'cancelled_at' => 'datetime', 'metadata' => 'array'];
    }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function paymentEvents(): HasMany { return $this->hasMany(PaymentEvent::class); }
}
