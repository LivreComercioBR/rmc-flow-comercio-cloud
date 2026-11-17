<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceWorkOrderRefund extends Model
{
    use ScopesToCompany;

    protected $fillable = ['company_id', 'service_work_order_id', 'payment_method', 'amount_cents', 'reference', 'reason', 'request_key', 'cash_session_id', 'created_by', 'approved_by', 'refunded_at'];
    protected function casts(): array { return ['refunded_at' => 'datetime']; }
    public function workOrder(): BelongsTo { return $this->belongsTo(ServiceWorkOrder::class, 'service_work_order_id'); }
    public function cashSession(): BelongsTo { return $this->belongsTo(CashSession::class, 'cash_session_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
