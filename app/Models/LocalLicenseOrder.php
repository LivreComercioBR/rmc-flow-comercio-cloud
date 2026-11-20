<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class LocalLicenseOrder extends Model
{
    public const PRODUCT = 'comercio-local';

    public const PRICE_CENTS = 9900;

    public const REFERENCE_PREFIX = 'local-license:';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'payment_updated_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function paymentReference(): string
    {
        return self::REFERENCE_PREFIX.$this->reference;
    }
}

