<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyInvitation extends Model
{
    use ScopesToCompany;

    protected $fillable = ['company_id', 'email', 'role', 'token_hash', 'invited_by', 'expires_at', 'accepted_at', 'accepted_by', 'revoked_at'];
    protected function casts(): array { return ['expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function inviter(): BelongsTo { return $this->belongsTo(User::class, 'invited_by'); }
}
