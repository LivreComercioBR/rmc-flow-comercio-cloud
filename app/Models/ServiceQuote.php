<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceQuote extends Model
{
    use ScopesToCompany;

    protected $fillable = [
        'company_id', 'quote_number', 'customer_id', 'service_appointment_id', 'subtotal_cents', 'notes',
        'expires_at', 'status', 'sent_at', 'decision_at', 'decision_by', 'decision_contact_name',
        'decision_channel', 'decision_notes', 'request_key', 'created_by',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'date', 'sent_at' => 'datetime', 'decision_at' => 'datetime'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function appointment(): BelongsTo { return $this->belongsTo(ServiceAppointment::class, 'service_appointment_id'); }
    public function items(): HasMany { return $this->hasMany(ServiceQuoteItem::class, 'service_quote_id')->orderBy('id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function decisionMaker(): BelongsTo { return $this->belongsTo(User::class, 'decision_by'); }
    public function workOrder(): HasOne { return $this->hasOne(ServiceWorkOrder::class, 'service_quote_id'); }
}
