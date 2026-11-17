<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceAppointment extends Model
{
    use ScopesToCompany;

    protected $fillable = ['company_id', 'customer_id', 'service_catalog_item_id', 'service_name', 'service_price_cents', 'scheduled_at', 'notes', 'status', 'request_key', 'created_by'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function service(): BelongsTo { return $this->belongsTo(ServiceCatalogItem::class, 'service_catalog_item_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function workOrder(): HasOne { return $this->hasOne(ServiceWorkOrder::class, 'service_appointment_id'); }
}
