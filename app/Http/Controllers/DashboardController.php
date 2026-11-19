<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\AccountPayable;
use App\Models\CashSession;
use App\Models\AccountReceivable;
use App\Models\CompanyMembership;
use App\Services\Cash\CashRegisterService;
use App\Support\CompanyContext;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

final class DashboardController extends Controller
{
    public function __invoke(CompanyContext $companyContext, CashRegisterService $cash, Request $request): View
    {
        $company = $companyContext->company();
        abort_unless($company, 403);
        $role = CompanyMembership::query()->where('company_id', $company->id)->where('user_id', auth()->id())->where('status', 'active')->value('role');
        $subscription = $company->currentSubscription();
        $subscriptionActive = $subscription?->status === 'active' && $subscription->current_period_end?->isFuture();
        $canManageSubscription = $role === 'Administrador';
        $checkoutEnabled = (bool) config('services.mercado_pago.checkout_enabled');
        $hasPriorApprovedPayment = $subscription?->paymentEvents()
            ->whereIn('status', ['processed', 'awaiting_billing_policy'])
            ->where('paid_amount_cents', '>', 0)
            ->exists() ?? false;
        $awaitingPaymentActivation = $subscription?->paymentEvents()
            ->where('status', 'awaiting_billing_policy')
            ->where('paid_amount_cents', '>', 0)
            ->exists() ?? false;
        $scheduledPlanId = ($subscription?->metadata ?? [])['scheduled_plan_change']['plan_id'] ?? null;
        $renewalPlan = $scheduledPlanId
            ? \App\Models\Plan::query()->whereKey((int) $scheduledPlanId)->where('active', true)->first()
            : $subscription?->plan;
        $renewalAvailable = $canManageSubscription
            && $checkoutEnabled
            && $hasPriorApprovedPayment
            && ! $awaitingPaymentActivation
            && $renewalPlan !== null;
        $upgradeAvailable = $canManageSubscription
            && $checkoutEnabled
            && $subscriptionActive
            && $subscription?->plan?->code === 'comercio-cloud-essencial'
            && ! $awaitingPaymentActivation;
        $supportEmail = config('services.support.email');
        $supportWhatsapp = config('services.support.whatsapp');
        $servicesEnabled = (bool) $company->modules()->where('module_key', 'services')->where('enabled', true)->exists();
        $canAccessServiceCatalog = $servicesEnabled && in_array($role, ['Administrador', 'Mecânico'], true);
        $customerCount = Customer::query()->where('company_id', $company->id)->where('status', 'active')->count();
        $productCount = \App\Models\Product::query()->where('status', 'active')->count();
        $lowStockCount = \App\Models\Product::query()->where('status', 'active')->where('minimum_stock', '>', 0)->whereColumn('stock_quantity', '<=', 'minimum_stock')->count();
        $openPayableCents = (int) AccountPayable::query()->whereIn('status', ['pending', 'partial'])
            ->selectRaw('COALESCE(SUM(amount_cents - paid_cents - cancelled_balance_cents), 0) as total')->value('total');
        $overduePayables = AccountPayable::query()->whereIn('status', ['pending', 'partial'])->where('due_date', '<', now()->toDateString())->count();
        $openReceivableCents = (int) AccountReceivable::query()->whereIn('status', ['pending', 'partial'])->selectRaw('COALESCE(SUM(amount_cents - paid_cents - cancelled_balance_cents), 0) as total')->value('total');
        $overdueReceivables = AccountReceivable::query()->whereIn('status', ['pending', 'partial'])->where('due_date', '<', now()->toDateString())->count();
        $openCashSession = CashSession::query()->where('status', 'open')->where('opened_by', $request->user()->id)->first();
        $cashBalanceCents = $openCashSession ? $cash->expectedBalance($openCashSession) : null;

        $hideValues = (bool) $request->session()->get('dashboard.hide_values.'.$company->id, false);

        return view('dashboard', compact('company', 'role', 'subscription', 'subscriptionActive', 'canManageSubscription', 'renewalPlan', 'renewalAvailable', 'upgradeAvailable', 'supportEmail', 'supportWhatsapp', 'customerCount', 'productCount', 'lowStockCount', 'openPayableCents', 'overduePayables', 'openReceivableCents', 'overdueReceivables', 'openCashSession', 'cashBalanceCents', 'hideValues', 'servicesEnabled', 'canAccessServiceCatalog'));
    }

    public function toggleValues(Request $request, CompanyContext $companyContext): RedirectResponse
    {
        $company = $companyContext->company();
        abort_unless($company, 403);
        $key = 'dashboard.hide_values.'.$company->id;
        $request->session()->put($key, ! (bool) $request->session()->get($key, false));

        return back();
    }
}
