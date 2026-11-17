<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySetting extends Model
{
    protected $fillable = ['company_id', 'setting_key', 'setting_value'];
    protected function casts(): array { return ['setting_value' => 'array']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
