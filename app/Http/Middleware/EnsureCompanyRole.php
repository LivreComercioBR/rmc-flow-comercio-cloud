<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Applies the initial operational access policy for the active company. */
final class EnsureCompanyRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = app(CompanyContext::class)->id();
        $membership = $request->user()?->companyMemberships()->where('company_id', $companyId)->where('status', 'active')->first();
        abort_unless($membership, Response::HTTP_FORBIDDEN);

        if ($membership->role === 'Administrador') return $next($request);

        $routeName = (string) $request->route()?->getName();
        if ($membership->role === 'Atendente') {
            $allowed = ['dashboard', 'dashboard.values.toggle', 'customers.index', 'customers.create', 'customers.store', 'customers.show', 'customers.edit', 'customers.update', 'customers.deactivate', 'customers.activate', 'products.index', 'sales.index', 'sales.create', 'sales.store', 'sales.show', 'sales.cancel', 'sales.refund', 'cash.index', 'cash.open', 'cash.show', 'cash.movement', 'cash.close'];
            abort_unless(in_array($routeName, $allowed, true), Response::HTTP_FORBIDDEN);
            return $next($request);
        }

        if ($membership->role === 'Mecânico') {
            abort_unless($routeName === 'dashboard' || str_starts_with($routeName, 'services.'), Response::HTTP_FORBIDDEN);
            $enabled = app(CompanyContext::class)->company()?->modules()->where('module_key', 'services')->where('enabled', true)->exists();
            abort_unless($enabled, Response::HTTP_FORBIDDEN);
            return $next($request);
        }

        abort(Response::HTTP_FORBIDDEN);
    }
}
