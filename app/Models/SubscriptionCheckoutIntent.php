<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionCheckoutIntent extends Model
{
    protected $fillable = [
        'token', 'plan_id', 'contact_email', 'purchase_reference', 'preference_id',
        'checkout_url', 'payment_id', 'payment_status', 'expected_amount_cents',
        'status', 'metadata', 'expires_at', 'paid_at', 'registered_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'expires_at' => 'datetime', 'paid_at' => 'datetime', 'registered_at' => 'datetime'];
    }

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
}
