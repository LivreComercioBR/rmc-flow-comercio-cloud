<?php

namespace App\Support;

use App\Models\Company;

/** Holds the company resolved and authorized for the current request. */
final class CompanyContext
{
    private ?Company $company = null;

    public function set(Company $company): void { $this->company = $company; }
    public function company(): ?Company { return $this->company; }
    public function id(): ?int { return $this->company?->getKey(); }
}
