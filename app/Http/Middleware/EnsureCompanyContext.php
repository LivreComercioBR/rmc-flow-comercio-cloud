<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Resolves the active company only from a server-side session and membership. */
final class EnsureCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, Response::HTTP_UNAUTHORIZED);

        $companyId = $request->session()->get('active_company_id');
        $membership = $user->companyMemberships()
            ->where('status', 'active')
            ->where('company_id', $companyId)
            ->whereHas('company', fn ($query) => $query->where('status', 'active'))
            ->with('company')
            ->first();

        if (! $membership || ! $membership->company) {\n            return redirect()->route('companies.index');\n        }
        app(CompanyContext::class)->set($membership->company);

        $hasActiveSubscription = $membership->company->subscriptions()
            ->where('status', 'active')->whereNotNull('current_period_end')->where('current_period_end', '>', now())->exists();
        if (! $hasActiveSubscription) {
            // Keep subscription self-service available after expiry so an administrator
            // can reduce seats or revoke invitations before renewing on a smaller plan.
            $billingAdminRoutes = ['company.users.index', 'company.users.update', 'company.invitations.revoke'];
            if (in_array((string) $request->route()?->getName(), $billingAdminRoutes, true)) {
                return $next($request);
            }
            return redirect()->route('subscription.status');
        }

        return $next($request);
    }
}
