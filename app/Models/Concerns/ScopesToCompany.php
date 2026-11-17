<?php

namespace App\Models\Concerns;

use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Fail-closed tenant scope for operational records that own a company_id. */
trait ScopesToCompany
{
    protected static function bootScopesToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            $companyId = app(CompanyContext::class)->id();
            if ($companyId === null) {
                $builder->whereRaw('1 = 0');
                return;
            }
            $builder->where($builder->getModel()->qualifyColumn('company_id'), $companyId);
        });

        static::creating(function (Model $model): void {
            $companyId = app(CompanyContext::class)->id();
            if ($companyId === null) {
                throw new LogicException('Cannot create a company-owned record without an authorized company context.');
            }
            $existingCompanyId = $model->getAttribute('company_id');
            if ($existingCompanyId !== null && (int) $existingCompanyId !== $companyId) {
                throw new LogicException('The record company does not match the authorized company context.');
            }
            $model->setAttribute('company_id', $companyId);
        });
        static::updating(function (Model $model): void {
            $companyId = app(CompanyContext::class)->id();
            if ($companyId === null || (int) $model->getAttribute('company_id') !== $companyId) {
                throw new LogicException('Cannot change a company-owned record outside its authorized company context.');
            }
        });
    }
}

