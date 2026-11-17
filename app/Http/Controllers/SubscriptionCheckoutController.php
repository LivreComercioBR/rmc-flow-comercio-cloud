<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\PaymentEvent;
use App\Models\CompanyInvitation;
use App\Models\CompanyMembership;
use App\Services\MercadoPago\PaymentGateway;
use App\Services\Subscriptions\PlanChangePricing;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

final class SubscriptionCheckoutController extends Controller
{
    public function scheduleDowngrade(Request $request, CompanyContext $context): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $this->authorizePlanChange($request, $company->id);
        $subscription = $company->currentSubscription();
        abort_unless($subscription && $subscription->status === 'active' && $subscription->current_period_end?->isFuture(), 422, 'A assinatura precisa estar ativa para agendar a troca.');
        $target = Plan::query()->where('code', 'comercio-cloud-essencial')->where('active', true)->firstOrFail();
        abort_if((int) $subscription->plan_id === (int) $target->id, 422, 'A empresa já está no plano Essencial.');
        $activeSeats = CompanyMembership::query()->where('company_id', $company->id)->where('status', 'active')->count();
        $activeEmails = DB::table('company_memberships')->join('users', 'users.id', '=', 'company_memberships.user_id')->where('company_memberships.company_id', $company->id)->where('company_memberships.status', 'active')->pluck('users.email')->map(fn ($email) => mb_strtolower($email));
        $reservedInvites = CompanyInvitation::query()->where('company_id', $company->id)->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->pluck('email')->map(fn ($email) => mb_strtolower($email))->unique()->diff($activeEmails)->count();
        if ($activeSeats + $reservedInvites > (int) ($target->limits['users'] ?? PHP_INT_MAX)) {
            throw ValidationException::withMessages(['checkout' => 'Antes de agendar o plano Essencial, reduza os acessos ativos e convites pendentes para no máximo 3 usuários.']);
        }
        $metadata = $subscription->metadata ?? [];
        $metadata['scheduled_plan_change'] = ['plan_id' => $target->id, 'plan_code' => $target->code, 'effective_at' => $subscription->current_period_end->toIso8601String()];
        $subscription->update(['metadata' => $metadata]);
        return back()->with('plan_notice', 'O plano Essencial passará a valer na renovação de '.$subscription->current_period_end->format('d/m/Y').'.');
    }

    public function cancelDowngrade(Request $request, CompanyContext $context): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $this->authorizePlanChange($request, $company->id);
        $subscription = $company->currentSubscription();
        if ($subscription) {
            $metadata = $subscription->metadata ?? [];
            $pendingReference = (string) ($metadata['pending_checkout']['purchase_reference'] ?? '');
            $paymentAwaitingActivation = $pendingReference !== '' && PaymentEvent::query()
                ->where('subscription_id', $subscription->id)
                ->where('purchase_reference', $pendingReference)
                ->where('status', 'awaiting_billing_policy')
                ->where('paid_amount_cents', '>', 0)
                ->exists();
            abort_if($paymentAwaitingActivation, 422, 'O pagamento da renovação já foi confirmado e aguarda ativação; não é possível cancelar a troca neste momento.');
            $checkoutExpiry = isset($metadata['last_checkout_expires_at']) ? \Illuminate\Support\Carbon::parse($metadata['last_checkout_expires_at']) : null;
            abort_if(($metadata['last_billing_stage'] ?? null) === 'renewal' && $checkoutExpiry?->isFuture(), 422, 'A troca não pode ser cancelada enquanto o checkout de renovação estiver válido. Aguarde a expiração do link.');
            unset($metadata['scheduled_plan_change']);
            if (($metadata['pending_checkout']['billing_stage'] ?? null) === 'renewal' && $checkoutExpiry?->isPast()) {
                unset($metadata['pending_checkout']);
                foreach (['last_checkout_preference_id', 'last_checkout_url', 'last_checkout_expires_at', 'last_billing_stage', 'last_checkout_sandbox', 'last_checkout_created_at'] as $key) unset($metadata[$key]);
            }
            $subscription->update(['metadata' => $metadata]);
        }
        return back()->with('plan_notice', 'A troca agendada foi cancelada.');
    }

    private function authorizePlanChange(Request $request, int $companyId): void
    {
        abort_unless(\App\Models\CompanyMembership::query()->where('company_id', $companyId)->where('user_id', $request->user()->id)->where('status', 'active')->where('role', 'Administrador')->exists(), 403);
    }

    public function __invoke(Request $request, CompanyContext $context, PaymentGateway $gateway, PlanChangePricing $pricing): RedirectResponse
    {
        abort_unless(config('services.mercado_pago.checkout_enabled', false), 503, 'A contratação ainda não está disponível.');
        $company = $context->company();
        abort_unless($company, 403);

        $selection = $request->validate([
            'plan_code' => ['nullable', 'string', 'max:64', Rule::exists('plans', 'code')->where('active', true)],
        ]);
        $subscription = $company->subscriptions()->with('plan')->orderByDesc('id')->first();
        $upgradePlan = null;
        $upgradeAmountCents = null;
        $hasPriorApprovedPayment = $subscription?->paymentEvents()
            ->whereIn('status', ['processed', 'awaiting_billing_policy'])
            ->where('paid_amount_cents', '>', 0)
            ->exists() ?? false;
        if ($hasPriorApprovedPayment && $subscription?->plan) {
            $plan = $subscription->plan;
            $scheduledPlanCode = ($subscription->metadata ?? [])['scheduled_plan_change']['plan_code'] ?? null;
            $isScheduledRenewalSelection = $subscription->current_period_end?->isPast()
                && $scheduledPlanCode !== null
                && ($selection['plan_code'] ?? $scheduledPlanCode) === $scheduledPlanCode;
            if ($subscription->status === 'active' && $subscription->current_period_end?->isFuture() && ($selection['plan_code'] ?? null) === 'comercio-cloud-equipe' && $plan->code === 'comercio-cloud-essencial') {
                $this->authorizePlanChange($request, $company->id);
                $upgradePlan = Plan::query()->where('code', 'comercio-cloud-equipe')->where('active', true)->firstOrFail();
                $upgradeAmountCents = $pricing->upgradeAmountCents($subscription, $upgradePlan);
                if ($upgradeAmountCents < 1) throw ValidationException::withMessages(['checkout' => 'O período atual está praticamente encerrado. Faça a renovação para trocar de plano.']);
                $plan = $upgradePlan;
            } elseif (! empty($selection['plan_code']) && $selection['plan_code'] !== $plan->code && ! $isScheduledRenewalSelection) {
                throw ValidationException::withMessages(['checkout' => 'O plano de uma assinatura ativa só pode ser alterado pelo fluxo de upgrade ou na renovação.']);
            }
            if (! $upgradePlan && $subscription->current_period_end?->isPast() && isset(($subscription->metadata ?? [])['scheduled_plan_change']['plan_id'])) {
                $scheduledId = (int) $subscription->metadata['scheduled_plan_change']['plan_id'];
                $plan = Plan::query()->whereKey($scheduledId)->where('active', true)->firstOrFail();
            }
        } else {
            $planCode = $selection['plan_code'] ?? $subscription?->plan?->code ?? config('services.mercado_pago.plan_code');
            $plan = Plan::query()->where('code', $planCode)->where('active', true)->firstOrFail();
            if ($subscription && (int) $subscription->plan_id !== (int) $plan->id) {
                $metadata = $subscription->metadata ?? [];
                $pendingCheckoutExpiresAt = isset($metadata['last_checkout_expires_at'])
                    ? \Illuminate\Support\Carbon::parse($metadata['last_checkout_expires_at'])
                    : null;
                if ($pendingCheckoutExpiresAt?->isFuture()) {
                    throw ValidationException::withMessages(['checkout' => 'A escolha do plano não pode ser alterada enquanto o checkout anterior ainda estiver válido. Aguarde a expiração do link e tente novamente.']);
                }
                foreach (['last_checkout_preference_id', 'last_checkout_url', 'last_checkout_expires_at', 'last_billing_stage', 'last_checkout_sandbox', 'last_checkout_created_at'] as $key) unset($metadata[$key]);
                $subscription->update(['plan_id' => $plan->id, 'metadata' => $metadata]);
                $subscription->setRelation('plan', $plan);
            }
        }
        if (! $upgradePlan && $subscription?->status === 'active' && $subscription->current_period_end?->isFuture()) {
            return redirect()->route('subscription.status')->withErrors([
                'checkout' => 'A assinatura está ativa até '.$subscription->current_period_end->format('d/m/Y').'. A renovação ficará disponível após o encerramento da vigência.',
            ]);
        }

        $stage = $upgradePlan ? 'upgrade' : ($hasPriorApprovedPayment ? 'renewal' : 'initial');
        $amountCents = $upgradePlan ? $upgradeAmountCents : ($stage === 'renewal' ? $plan->renewal_price_cents : $plan->initial_price_cents);
        $notificationUrl = (string) config('services.mercado_pago.notification_url', '');
        $returnUrl = route('subscription.status');
        abort_unless(filter_var($returnUrl, FILTER_VALIDATE_URL) && str_starts_with($returnUrl, 'https://'), 503, 'APP_URL must be configured with the system HTTPS domain before checkout is enabled.');
        abort_unless(filter_var($notificationUrl, FILTER_VALIDATE_URL) && str_starts_with($notificationUrl, 'https://'), 503, 'Configure a URL HTTPS pública para o webhook do Mercado Pago.');

        if (! $subscription) {
            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'provider' => 'mercado_pago',
                'provider_reference' => 'company:'.$company->id,
                'status' => 'pending',
                'metadata' => ['product_code' => (string) config('services.subscription_panel.product_code')],
            ]);
        }

        $metadata = $subscription->metadata ?? [];
        $lastCheckoutUrl = $metadata['last_checkout_url'] ?? null;
        $sandbox = (bool) config('services.mercado_pago.sandbox', false);
        $lastCheckoutExpiresAt = isset($metadata['last_checkout_expires_at'])
            ? \Illuminate\Support\Carbon::parse($metadata['last_checkout_expires_at'])
            : null;
        if (($metadata['last_billing_stage'] ?? null) === $stage
            && ($metadata['last_checkout_sandbox'] ?? null) === $sandbox
            && ! $sandbox
            && $lastCheckoutExpiresAt?->isFuture()
            && is_string($lastCheckoutUrl)
            && filter_var($lastCheckoutUrl, FILTER_VALIDATE_URL)) {
            return redirect()->away($lastCheckoutUrl);
        }

        $preferenceExpiresAt = now()->addDays(3);
        $purchaseReference = 'company:'.$company->id.':subscription:'.$subscription->id.':'.$stage.':'.Str::uuid();

        $payload = [
            'items' => [[
                'id' => $plan->code.'-'.$stage,
                'title' => $plan->name.' - '.($stage === 'initial' ? 'primeira compra anual' : ($stage === 'upgrade' ? 'upgrade proporcional' : 'renovação anual')),
                'description' => 'Até '.($plan->limits['users'] ?? 'ilimitados').' usuários; computadores ilimitados. '.($stage === 'initial' ? 'Primeira compra anual.' : ($stage === 'upgrade' ? 'Upgrade proporcional pelo período restante.' : 'Renovação anual.')),
                'quantity' => 1,
                'currency_id' => 'BRL',
                'unit_price' => $amountCents / 100,
            ]],
            'payer' => array_filter([
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ]),
            'external_reference' => 'company:'.$company->id,
            'back_urls' => [
                'success' => $returnUrl.'?payment=approved',
                'pending' => $returnUrl.'?payment=pending',
                'failure' => $returnUrl.'?payment=failure',
            ],
            'auto_return' => 'approved',
            'expires' => true,
            'expiration_date_from' => now()->toIso8601String(),
            'expiration_date_to' => $preferenceExpiresAt->toIso8601String(),
            'metadata' => [
                'company_id' => $company->id,
                'subscription_id' => $subscription->id,
                'product_code' => (string) config('services.subscription_panel.product_code'),
                'billing_stage' => $stage,
                'plan_code' => $plan->code,
                'expected_amount_cents' => $amountCents,
                'user_limit' => $plan->limits['users'] ?? null,
                'purchase_reference' => $purchaseReference,
            ],
        ];

        if (! $sandbox) {
            $payload['notification_url'] = $notificationUrl;
        }

        try {
            $checkout = $gateway->createCheckout($payload);
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors(['checkout' => 'Não foi possível iniciar a contratação agora. Tente novamente em alguns minutos.']);
        }

        $checkoutUrl = $checkout['init_point'] ?? null;
        abort_unless(is_string($checkoutUrl) && filter_var($checkoutUrl, FILTER_VALIDATE_URL), 502, 'Mercado Pago did not return a valid checkout URL.');

        $updatedMetadata = array_merge($subscription->metadata ?? [], [
                'last_checkout_preference_id' => $checkout['id'] ?? null,
                'last_checkout_url' => $checkoutUrl,
                'last_checkout_expires_at' => $preferenceExpiresAt->toIso8601String(),
                'last_billing_stage' => $stage,
                'last_checkout_sandbox' => $sandbox,
                'last_checkout_created_at' => now()->toIso8601String(),
            ]);
        $updatedMetadata['pending_checkout'] = [
            'plan_id' => $plan->id,
            'plan_code' => $plan->code,
            'billing_stage' => $stage,
            'expected_amount_cents' => $amountCents,
            'purchase_reference' => $purchaseReference,
            'preference_id' => $checkout['id'] ?? null,
            'expires_at' => $preferenceExpiresAt->toIso8601String(),
        ];
        if ($upgradePlan) {
            $updatedMetadata['pending_upgrade'] = [
                'plan_id' => $upgradePlan->id,
                'plan_code' => $upgradePlan->code,
                'expected_amount_cents' => $amountCents,
                'purchase_reference' => $purchaseReference,
                'preference_id' => $checkout['id'] ?? null,
                'expires_at' => $preferenceExpiresAt->toIso8601String(),
            ];
        }
        $subscription->update(['metadata' => $updatedMetadata]);

        return redirect()->away($checkoutUrl);
    }
}
