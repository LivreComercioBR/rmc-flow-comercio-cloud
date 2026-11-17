<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceWorkOrder extends Model
{
    use ScopesToCompany;

    protected $fillable = ['company_id', 'work_order_number', 'customer_id', 'service_quote_id', 'service_appointment_id', 'service_asset_id', 'asset_type', 'asset_description', 'asset_identifier', 'asset_manufacturer', 'asset_model', 'asset_year', 'asset_color', 'status', 'complaint', 'diagnosis', 'notes', 'warranty_days', 'opened_at', 'completed_at', 'finalized_at', 'stock_applied_at', 'stock_reversed_at', 'cancelled_at', 'cancelled_by', 'cancelled_approved_by', 'cancellation_reason', 'created_by'];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'completed_at' => 'datetime', 'finalized_at' => 'datetime', 'stock_applied_at' => 'datetime', 'stock_reversed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function quote(): BelongsTo { return $this->belongsTo(ServiceQuote::class, 'service_quote_id'); }
    public function appointment(): BelongsTo { return $this->belongsTo(ServiceAppointment::class, 'service_appointment_id'); }
    public function asset(): BelongsTo { return $this->belongsTo(ServiceAsset::class, 'service_asset_id'); }
    public function items(): HasMany { return $this->hasMany(ServiceWorkOrderItem::class, 'service_work_order_id')->orderBy('id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function cancelledBy(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function cancellationApprover(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_approved_by'); }
    public function receivable(): HasOne { return $this->hasOne(AccountReceivable::class, 'service_work_order_id'); }
    public function refunds(): HasMany { return $this->hasMany(ServiceWorkOrderRefund::class, 'service_work_order_id')->orderBy('refunded_at'); }

    public function totalCents(): int
    {
        return (int) $this->items()->sum('total_cents');
    }
}
