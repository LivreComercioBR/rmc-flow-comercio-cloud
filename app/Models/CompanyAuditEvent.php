<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyAuditEvent extends Model
{
    use ScopesToCompany;

    public $timestamps = false;
    protected $fillable = ['company_id', 'actor_id', 'approved_by', 'event_key', 'subject_type', 'subject_id', 'description', 'metadata', 'ip_address', 'user_agent', 'created_at'];
    protected function casts(): array { return ['metadata' => 'array', 'created_at' => 'datetime']; }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
