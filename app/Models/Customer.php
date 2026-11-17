<?php

namespace App\Models;

use App\Models\Concerns\ScopesToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use ScopesToCompany;

    protected $fillable = [
        'company_id', 'name', 'document', 'email', 'phone', 'address', 'notes', 'status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sales(): HasMany { return $this->hasMany(Sale::class)->latest('sold_at'); }
    public function receivables(): HasMany { return $this->hasMany(AccountReceivable::class)->orderBy('due_date'); }
    public function serviceAppointments(): HasMany { return $this->hasMany(ServiceAppointment::class)->orderBy('scheduled_at'); }
    public function serviceAssets(): HasMany { return $this->hasMany(ServiceAsset::class)->orderBy('name'); }
}
