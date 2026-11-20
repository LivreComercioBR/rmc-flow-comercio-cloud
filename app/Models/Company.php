<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    protected $fillable = ['legal_name', 'trade_name', 'document', 'email', 'phone', 'timezone', 'status', 'last_sale_number', 'last_quote_number'];

    public function memberships(): HasMany { return $this->hasMany(CompanyMembership::class); }
    public function settings(): HasMany { return $this->hasMany(CompanySetting::class); }
    public function modules(): HasMany { return $this->hasMany(CompanyModule::class); }
    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }

    public function logoPath(): ?string
    {
        $setting = $this->settings()->where('setting_key', 'company.logo')->first()?->setting_value;
        $path = is_array($setting) ? ($setting['path'] ?? null) : null;
        $prefix = 'companies/'.$this->getKey().'/logos/';

        return is_string($path) && str_starts_with($path, $prefix) && ! str_contains($path, '..') ? $path : null;
    }

    public function logoUrl(): ?string
    {
        $path = $this->logoPath();

        return $path && Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : null;
    }

    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()->with('plan')->orderByDesc('current_period_end')->orderByDesc('id')->first();
    }
}

