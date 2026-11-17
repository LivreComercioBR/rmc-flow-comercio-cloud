<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvent extends Model
{
    protected $fillable = ['subscription_id', 'provider', 'provider_event_id', 'purchase_reference', 'event_type', 'billing_stage', 'expected_amount_cents', 'paid_amount_cents', 'status', 'received_at', 'processed_at', 'panel_sync_status', 'panel_synced_at', 'panel_sync_attempts', 'panel_sync_error', 'payload', 'processing_error'];
    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'processed_at' => 'datetime', 'panel_synced_at' => 'datetime', 'payload' => 'array'];
    }
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
}
