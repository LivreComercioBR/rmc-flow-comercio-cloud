<?php

namespace App\Providers;

use App\Support\CompanyContext;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CompanyContext::class);
    }

    public function boot(): void
    {
        // Keep implicit indexed strings within MySQL 5.7's legacy utf8mb4 key limit.
        Schema::defaultStringLength(191);
    }
}

