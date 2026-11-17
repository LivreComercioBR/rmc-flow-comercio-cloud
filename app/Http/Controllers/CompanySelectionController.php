<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CompanySelectionController extends Controller
{
    public function index(Request $request): View
    {
        $memberships = $request->user()->companyMemberships()
            ->where('status', 'active')
            ->whereHas('company', fn ($query) => $query->where('status', 'active'))
            ->with('company')
            ->orderBy('id')
            ->get();

        return view('companies.index', ['memberships' => $memberships]);
    }

    public function activate(Request $request, int $company): RedirectResponse
    {
        $membership = $request->user()->companyMemberships()
            ->where('company_id', $company)
            ->where('status', 'active')
            ->whereHas('company', fn ($query) => $query->where('status', 'active'))
            ->firstOrFail();

        $request->session()->regenerate();
        $request->session()->put('active_company_id', $membership->company_id);

        $hasActiveSubscription = $membership->company->subscriptions()
            ->where('status', 'active')->whereNotNull('current_period_end')->where('current_period_end', '>', now())->exists();
        return redirect()->route($hasActiveSubscription ? 'dashboard' : 'subscription.status');
    }
}
