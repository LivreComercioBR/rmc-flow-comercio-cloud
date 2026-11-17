<?php

namespace App\Http\Controllers;

use App\Models\CompanyMembership;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Support\CompanyContext;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class CompanyUserController extends Controller
{
    public function index(Request $request, CompanyContext $context): View
    {
        $company = $context->company();
        abort_unless($company, 403);
        $this->authorizeAdministrator($request, $company->id);
        $memberships = CompanyMembership::query()->with('user')->where('company_id', $company->id)->orderBy('id')->get();
        $invitations = CompanyInvitation::query()->with('inviter')->where('company_id', $company->id)->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->latest()->get();
        $subscription = $company->currentSubscription();
        $scheduledPlanId = ($subscription?->metadata ?? [])['scheduled_plan_change']['plan_id'] ?? null;
        $scheduledPlan = $scheduledPlanId ? \App\Models\Plan::query()->whereKey((int) $scheduledPlanId)->where('active', true)->first() : null;
        $currentSeatLimit = $subscription?->plan?->limits['users'] ?? null;
        $seatLimit = $scheduledPlan?->limits['users'] ?? $currentSeatLimit;
        $activeSeats = $memberships->where('status', 'active')->count();
        return view('companies.users', compact('company', 'memberships', 'invitations', 'seatLimit', 'currentSeatLimit', 'scheduledPlan', 'activeSeats'));
    }

    public function update(Request $request, int $membership, CompanyContext $context): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $this->authorizeAdministrator($request, $company->id);
        $data = $request->validate([
            'role' => ['required', Rule::in(['Administrador', 'Atendente', 'Mecânico'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        DB::transaction(function () use ($company, $membership, $data, $request): void {
            $lockedCompany = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $record = CompanyMembership::query()->where('company_id', $company->id)->whereKey($membership)->lockForUpdate()->firstOrFail();
            $before = ['role' => $record->role, 'status' => $record->status];
            if ($record->status !== 'active' && $data['status'] === 'active') {
                $subscription = $lockedCompany->currentSubscription();
                if (! $subscription || $subscription->status !== 'active' || ! $subscription->current_period_end?->isFuture()) {
                    throw ValidationException::withMessages(['status' => 'A assinatura está vencida. Renove antes de reativar usuários.']);
                }
                $scheduledPlanId = ($subscription?->metadata ?? [])['scheduled_plan_change']['plan_id'] ?? null;
                $scheduledPlan = $scheduledPlanId ? \App\Models\Plan::query()->whereKey((int) $scheduledPlanId)->where('active', true)->first() : null;
                $seatLimit = $scheduledPlan?->limits['users'] ?? $subscription?->plan?->limits['users'] ?? null;
                $activeCount = CompanyMembership::query()->where('company_id', $company->id)->where('status', 'active')->count();
                if ($seatLimit !== null && $activeCount >= (int) $seatLimit) throw ValidationException::withMessages(['status' => 'O limite de usuários deste plano já foi atingido.']);
            }
            $removingAdmin = $record->role === 'Administrador' && $record->status === 'active' && ($data['role'] !== 'Administrador' || $data['status'] !== 'active');
            if ($removingAdmin) {
                $otherActiveAdmins = CompanyMembership::query()->where('company_id', $company->id)->where('status', 'active')->where('role', 'Administrador')->where('id', '<>', $record->id)->lockForUpdate()->count();
                if ($otherActiveAdmins < 1) throw ValidationException::withMessages(['role' => 'A empresa precisa manter pelo menos um administrador ativo.']);
            }
            $record->update($data);
            app(AuditTrailService::class)->record((int) $company->id, 'company_user.updated', 'Acesso de '.$record->user()->value('name').' atualizado.', (int) $request->user()->id, 'company_membership', (int) $record->id, ['before' => $before, 'after' => $data]);
        });
        return back()->with('users_notice', 'Acesso atualizado para esta empresa.');
    }

    private function authorizeAdministrator(Request $request, int $companyId): void
    {
        abort_unless($request->user()->companyMemberships()->where('company_id', $companyId)->where('status', 'active')->where('role', 'Administrador')->exists(), 403);
    }
}
