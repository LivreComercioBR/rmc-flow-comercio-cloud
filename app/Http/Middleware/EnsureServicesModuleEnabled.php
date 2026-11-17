<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureServicesModuleEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = app(CompanyContext::class)->company();
        abort_unless($company && $company->modules()->where('module_key', 'services')->where('enabled', true)->exists(), Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
