<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['code', 'name', 'billing_cycle', 'price_cents', 'initial_price_cents', 'renewal_price_cents', 'limits', 'features', 'active'];
    protected function casts(): array { return ['limits' => 'array', 'features' => 'array', 'active' => 'boolean']; }
    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }
}
