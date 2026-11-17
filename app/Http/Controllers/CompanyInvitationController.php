<?php

namespace App\Http\Controllers;

use App\Mail\CompanyInvitationMail;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Services\AuditTrailService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

final class CompanyInvitationController extends Controller
{
    public function store(Request $request, CompanyContext $context): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $this->authorizeAdministrator($request, $company->id);
        if (! in_array(config('mail.default'), ['smtp', 'ses', 'ses-v2', 'mailgun', 'postmark', 'resend', 'sendmail'], true)) {
            throw ValidationException::withMessages(['email' => 'Configure um serviço de e-mail real antes de enviar convites. O mailer atual não entrega mensagens.']);
        }
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
            'role' => ['required', Rule::in(['Administrador', 'Atendente', 'Mecânico'])],
        ]);
        $email = mb_strtolower(trim($data['email']));
        $user = User::query()->where('email', $email)->first();
        if ($user && CompanyMembership::query()->where('company_id', $company->id)->where('user_id', $user->id)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['email' => 'Este usuário já tem acesso ativo a esta empresa.']);
        }

        $token = Str::random(64);
        $invitation = DB::transaction(function () use ($company, $email, $data, $token, $request): CompanyInvitation {
            $lockedCompany = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $subscription = $lockedCompany->currentSubscription();
            $scheduledPlanId = ($subscription?->metadata ?? [])['scheduled_plan_change']['plan_id'] ?? null;
            $scheduledPlan = $scheduledPlanId ? \App\Models\Plan::query()->whereKey((int) $scheduledPlanId)->where('active', true)->first() : null;
            $seatLimit = $scheduledPlan?->limits['users'] ?? $subscription?->plan?->limits['users'] ?? null;
            if ($seatLimit !== null) {
                $activeCount = CompanyMembership::query()->where('company_id', $company->id)->where('status', 'active')->count();
                $activeEmails = DB::table('company_memberships')->join('users', 'users.id', '=', 'company_memberships.user_id')->where('company_memberships.company_id', $company->id)->where('company_memberships.status', 'active')->pluck('users.email')->map(fn ($value) => mb_strtolower($value));
                $reservedEmails = CompanyInvitation::query()->where('company_id', $company->id)->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->pluck('email')->map(fn ($value) => mb_strtolower($value))->unique()->diff($activeEmails);
                if ($activeCount + $reservedEmails->count() >= (int) $seatLimit && ! $reservedEmails->contains($email)) {
                    throw ValidationException::withMessages(['email' => 'O limite de usuários deste plano já foi atingido, incluindo convites pendentes.']);
                }
            }
            CompanyInvitation::query()->where('company_id', $company->id)->where('email', $email)->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $invitation = CompanyInvitation::create([
                'company_id' => $company->id,
                'email' => $email,
                'role' => $data['role'],
                'token_hash' => hash('sha256', $token),
                'invited_by' => $request->user()->id,
                'expires_at' => now()->addDays(7),
            ]);
            app(AuditTrailService::class)->record((int) $company->id, 'company_user.invited', 'Convite enviado para '.$email.'.', (int) $request->user()->id, 'company_invitation', (int) $invitation->id, ['email' => $email, 'role' => $data['role']]);
            return $invitation;
        });

        try {
            $invitation->load('company');
            Mail::to($email)->send(new CompanyInvitationMail($invitation, route('company-invitations.show', ['token' => $token])));
        } catch (\Throwable $exception) {
            DB::transaction(function () use ($invitation, $company, $request): void {
                $invitation->update(['revoked_at' => now()]);
                app(AuditTrailService::class)->record((int) $company->id, 'company_user.invitation_failed', 'Falha no envio de convite.', (int) $request->user()->id, 'company_invitation', (int) $invitation->id);
            });
            Log::warning('Company invitation email delivery failed.', ['exception_type' => get_class($exception)]);
            throw ValidationException::withMessages(['email' => 'Não foi possível enviar o convite. Confira a configuração de e-mail e tente novamente.']);
        }
        return back()->with('users_notice', 'Convite enviado para '.$email.'. Ele expira em 7 dias.');
    }

    public function revoke(Request $request, int $invitation, CompanyContext $context): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $this->authorizeAdministrator($request, $company->id);
        DB::transaction(function () use ($company, $invitation, $request): void {
            $record = CompanyInvitation::query()->where('company_id', $company->id)->whereKey($invitation)->whereNull('accepted_at')->whereNull('revoked_at')->lockForUpdate()->firstOrFail();
            $record->update(['revoked_at' => now()]);
            app(AuditTrailService::class)->record((int) $company->id, 'company_user.invitation_revoked', 'Convite de '.$record->email.' revogado.', (int) $request->user()->id, 'company_invitation', (int) $record->id, ['email' => $record->email]);
        });
        return back()->with('users_notice', 'Convite revogado.');
    }

    public function show(string $token): View
    {
        $invitation = $this->findValidInvitation($token);
        if (Auth::check() && mb_strtolower(Auth::user()->email) !== $invitation->email) abort(403, 'Entre com a conta correspondente ao e-mail do convite.');
        $existingUser = User::query()->where('email', $invitation->email)->exists();
        $needsPassword = ! Auth::check();
        return view('companies.accept-invitation', compact('invitation', 'existingUser', 'needsPassword'));
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findValidInvitation($token);
        $existingUser = User::query()->where('email', $invitation->email)->exists();
        $passwordRules = ['nullable', 'string', Rule::requiredIf(fn () => ! Auth::check())];
        if (! $existingUser && ! Auth::check()) $passwordRules = ['required', 'string', 'min:10', 'confirmed'];
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:190', Rule::requiredIf(fn () => ! Auth::check() && ! $existingUser)],
            'password' => $passwordRules,
        ]);
        $user = DB::transaction(function () use ($invitation, $data, $request): User {
            $company = Company::query()->whereKey($invitation->company_id)->lockForUpdate()->firstOrFail();
            app(CompanyContext::class)->set($company);
            $locked = CompanyInvitation::withoutGlobalScope('company')->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if ($locked->accepted_at || $locked->revoked_at || $locked->expires_at->isPast()) {
                throw ValidationException::withMessages(['invitation' => 'Este convite não está mais válido.']);
            }
            $existing = User::query()->where('email', $locked->email)->lockForUpdate()->first();
            if (Auth::check()) {
                if (mb_strtolower(Auth::user()->email) !== $locked->email) throw ValidationException::withMessages(['invitation' => 'Entre com a conta correspondente ao e-mail do convite.']);
                if (Auth::user()->status !== 'active') throw ValidationException::withMessages(['invitation' => 'Esta conta está inativa. Contate o administrador.']);
                $user = Auth::user();
            } elseif ($existing) {
                if ($existing->status !== 'active' || ! Hash::check($data['password'], $existing->password)) throw ValidationException::withMessages(['password' => 'A senha informada não corresponde à conta existente.']);
                $user = $existing;
            } else {
                $user = User::create(['name' => trim($data['name']), 'email' => $locked->email, 'password' => $data['password'], 'status' => 'active']);
            }

            // The single-use invitation token was delivered to this exact email address.
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $membership = CompanyMembership::query()->where('company_id', $locked->company_id)->where('user_id', $user->id)->lockForUpdate()->first();
            if ($membership && $membership->status === 'active') throw ValidationException::withMessages(['invitation' => 'Esta conta já possui acesso ativo à empresa.']);
            $subscription = $company->currentSubscription();
            $scheduledPlanId = ($subscription?->metadata ?? [])['scheduled_plan_change']['plan_id'] ?? null;
            $scheduledPlan = $scheduledPlanId ? \App\Models\Plan::query()->whereKey((int) $scheduledPlanId)->where('active', true)->first() : null;
            $seatLimit = $scheduledPlan?->limits['users'] ?? $subscription?->plan?->limits['users'] ?? null;
            $activeCount = CompanyMembership::query()->where('company_id', $company->id)->where('status', 'active')->count();
            if ($seatLimit !== null && $activeCount >= (int) $seatLimit) throw ValidationException::withMessages(['invitation' => 'A empresa atingiu o limite de usuários do plano. Desative um acesso ou contate o suporte para solicitar a alteração do plano.']);
            if ($membership) $membership->update(['role' => $locked->role, 'status' => 'active']);
            else $membership = CompanyMembership::create(['company_id' => $locked->company_id, 'user_id' => $user->id, 'role' => $locked->role, 'status' => 'active']);

            $locked->update(['accepted_at' => now(), 'accepted_by' => $user->id]);
            app(AuditTrailService::class)->record((int) $company->id, 'company_user.invitation_accepted', 'Convite aceito por '.$user->email.'.', (int) $user->id, 'company_membership', (int) $membership->id, ['role' => $locked->role, 'invited_by' => $locked->invited_by]);
            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('active_company_id', $invitation->company_id);
        $hasActiveSubscription = $invitation->company->subscriptions()->where('status', 'active')->whereNotNull('current_period_end')->where('current_period_end', '>', now())->exists();
        return redirect()->route($hasActiveSubscription ? 'dashboard' : 'subscription.status');
    }

    private function findValidInvitation(string $token): CompanyInvitation
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/', $token) === 1, 404);
        $invitation = CompanyInvitation::withoutGlobalScope('company')->with('company')
            ->where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_if($invitation->accepted_at || $invitation->revoked_at || $invitation->expires_at->isPast(), 410, 'Este convite expirou ou já foi utilizado.');
        return $invitation;
    }

    private function authorizeAdministrator(Request $request, int $companyId): void
    {
        abort_unless($request->user()->companyMemberships()->where('company_id', $companyId)->where('status', 'active')->where('role', 'Administrador')->exists(), 403);
    }
}
