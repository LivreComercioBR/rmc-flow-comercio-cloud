<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCompanyContextForBilling
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, Response::HTTP_UNAUTHORIZED);
        $companyId = $request->session()->get('active_company_id');
        $membership = $user->companyMemberships()->where('status', 'active')->where('company_id', $companyId)
            ->whereHas('company', fn ($query) => $query->where('status', 'active'))->with('company')->first();
        if (! $membership || ! $membership->company) {\n            return redirect()->route('companies.index');\n        }
        app(CompanyContext::class)->set($membership->company);
        return $next($request);
    }
}
