<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\Subscriptions\PlanChangePricing;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SubscriptionStatusController extends Controller
{
    public function __invoke(Request $request, CompanyContext $context, PlanChangePricing $pricing): View
    {
        $company = $context->company();
        $subscription = $company->currentSubscription();
        $active = $subscription && $subscription->status === 'active' && $subscription->current_period_end?->isFuture();
        $paymentEvents = ($subscription?->paymentEvents()->latest('received_at')->get() ?? collect())
            ->map(fn ($event) => $this->presentPaymentEvent($event));
        $panelSyncEvent = $subscription?->paymentEvents()
            ->where('status', 'processed')
            ->where(function ($query): void {
                $query->whereIn('panel_sync_status', ['pending', 'failed'])
                    ->orWhere('panel_sync_status', 'syncing');
            })
            ->latest('processed_at')
            ->first();
        $panelRetryAvailable = $panelSyncEvent && (
            in_array($panelSyncEvent->panel_sync_status, ['pending', 'failed'], true)
            || $panelSyncEvent->updated_at?->lte(now()->subMinutes(5))
        );
        $panelSyncConfigurationMissing = $panelSyncEvent?->panel_sync_error === 'panel_configuration_incomplete';
        $lastPanelSync = $subscription?->paymentEvents()
            ->where('panel_sync_status', 'synced')
            ->latest('panel_synced_at')
            ->first();
        $hasPriorPayment = $paymentEvents->contains(fn ($event) => in_array($event->status, ['processed', 'awaiting_billing_policy'], true) && (int) $event->paid_amount_cents > 0);
        $awaitingActivation = $paymentEvents->contains(fn ($event) => $event->status === 'awaiting_billing_policy' && (int) $event->paid_amount_cents > 0);
        $latestEvent = $paymentEvents->first();

        $paymentNotice = $latestEvent?->status === 'duplicate_order'
            ? ['type' => 'warning', 'text' => 'O Mercado Pago confirmou mais de um pagamento para o mesmo checkout. A assinatura não foi estendida em duplicidade; entre em contato com o suporte para conferir o estorno do pagamento repetido.']
            : ($awaitingActivation
            ? ['type' => 'warning', 'text' => $latestEvent?->billing_stage === 'upgrade'
                ? 'O pagamento do upgrade foi confirmado pelo Mercado Pago. O plano Equipe ainda aguarda a configuração de ativação da assinatura; até lá, permanece valendo o plano atual.'
                : 'O pagamento foi confirmado pelo Mercado Pago. A ativação ou renovação do acesso ainda aguarda a configuração de ativação da assinatura.']
            : ($active ? null : match ($latestEvent?->status) {
            'awaiting_billing_policy' => ['type' => 'warning', 'text' => 'O pagamento foi confirmado pelo Mercado Pago. O acesso ainda aguarda a configuração de ativação da assinatura.'],
            'observed' => ['type' => 'warning', 'text' => 'O pagamento ainda não aparece como aprovado. A confirmação será atualizada quando o Mercado Pago enviar uma nova notificação.'],
            'rejected' => ['type' => 'error', 'text' => 'O valor ou a moeda do pagamento não corresponde ao plano. O acesso não foi liberado. Entre em contato com o suporte antes de pagar novamente.'],
            'failed' => ['type' => 'warning', 'text' => 'A confirmação do pagamento teve uma falha técnica. O sistema poderá processar a notificação novamente.'],
            'processed' => ['type' => 'warning', 'text' => 'O último pagamento foi aprovado, mas a assinatura não está vigente. Faça a renovação para recuperar o acesso.'],
            default => $request->query->has('payment')
                ? ['type' => 'warning', 'text' => 'Retorno recebido do checkout. A página aguarda a confirmação do webhook; o parâmetro de retorno não confirma o pagamento.']
                : null,
        }));

        $canCheckout = (bool) config('services.mercado_pago.checkout_enabled') && ! $awaitingActivation;
        $availablePlans = Plan::query()->where('active', true)->orderBy('initial_price_cents')->get();
        $scheduledPlanId = ($subscription?->metadata ?? [])['scheduled_plan_change']['plan_id'] ?? null;
        $renewalPlan = $hasPriorPayment && $subscription?->current_period_end?->isPast() && $scheduledPlanId
            ? $availablePlans->firstWhere('id', (int) $scheduledPlanId)
            : null;
        $checkoutPlans = $hasPriorPayment && $subscription?->plan ? collect([$renewalPlan ?? $subscription->plan]) : $availablePlans;
        $canRetryPanelSync = $company->memberships()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->where('role', 'Administrador')
            ->exists();
        $canManagePlan = $company->memberships()->where('user_id', $request->user()->id)->where('status', 'active')->where('role', 'Administrador')->exists();
        $upgradeQuoteCents = null;
        $teamPlan = $availablePlans->firstWhere('code', 'comercio-cloud-equipe');
        if ($active && $canManagePlan && $canCheckout && $subscription?->plan?->code === 'comercio-cloud-essencial' && $teamPlan) {
            $metadata = $subscription->metadata ?? [];
            $checkoutExpiry = isset($metadata['last_checkout_expires_at']) ? \Illuminate\Support\Carbon::parse($metadata['last_checkout_expires_at']) : null;
            $hasReusableUpgrade = ($metadata['last_billing_stage'] ?? null) === 'upgrade'
                && ($metadata['last_checkout_sandbox'] ?? null) === (bool) config('services.mercado_pago.sandbox', false)
                && $checkoutExpiry?->isFuture()
                && ($metadata['pending_checkout']['plan_code'] ?? null) === 'comercio-cloud-equipe'
                && is_string($metadata['last_checkout_url'] ?? null)
                && filter_var($metadata['last_checkout_url'], FILTER_VALIDATE_URL)
                && isset($metadata['pending_checkout']['expected_amount_cents']);
            $upgradeQuoteCents = $hasReusableUpgrade
                ? (int) $metadata['pending_checkout']['expected_amount_cents']
                : $pricing->upgradeAmountCents($subscription, $teamPlan);
            if ($upgradeQuoteCents < 1) $upgradeQuoteCents = null;
        }

        return view('subscription.status', compact('company', 'subscription', 'active', 'paymentEvents', 'hasPriorPayment', 'awaitingActivation', 'paymentNotice', 'canCheckout', 'availablePlans', 'checkoutPlans', 'panelSyncEvent', 'panelRetryAvailable', 'panelSyncConfigurationMissing', 'lastPanelSync', 'canRetryPanelSync', 'canManagePlan', 'upgradeQuoteCents'));
    }

    private function presentPaymentEvent(\App\Models\PaymentEvent $event): \App\Models\PaymentEvent
    {
        [$label, $detail] = match ($event->status) {
            'processed' => ['Confirmado', 'Pagamento validado e aplicado à assinatura.'],
            'awaiting_billing_policy' => ['Aprovado; aguardando ativação', $event->billing_stage === 'upgrade'
                ? 'O plano atual permanece ativo até a ativação do upgrade.'
                : 'A renovação ou ativação aguarda a configuração de liberação.'],
            'observed' => ['Pendente de aprovação', 'O acesso não foi alterado; aguardamos a atualização do pagamento.'],
            'rejected' => [
                'Não aplicado',
                str_starts_with((string) $event->processing_error, 'Payment metadata does not match')
                    ? 'Os dados deste pagamento não correspondem ao checkout pendente.'
                    : (str_starts_with((string) $event->processing_error, 'Renewal checkout plan does not match')
                        ? 'O plano cobrado não corresponde ao plano de renovação esperado.'
                        : 'O valor ou a moeda não corresponde ao checkout esperado.'),
            ],
            'duplicate_order' => ['Pagamento repetido; conferir estorno', 'A assinatura não foi alterada uma segunda vez. Contate o suporte para revisar a cobrança repetida.'],
            'failed' => ['Falha no processamento', 'O sistema poderá tentar processar a notificação novamente.'],
            'ignored' => ['Notificação ignorada', 'A notificação não corresponde a uma cobrança desta assinatura.'],
            'received' => ['Em processamento', 'Aguardando consulta e validação do pagamento.'],
            default => ['Em análise', 'O evento ainda está sendo verificado.'],
        };

        $event->setAttribute('display_status', $label);
        $event->setAttribute('display_detail', $detail);
        $event->setAttribute('display_panel_sync', match (true) {
            $event->status !== 'processed' => null,
            $event->panel_sync_status === 'synced' => 'Painel central sincronizado',
            $event->panel_sync_status === 'syncing' => 'Sincronização com painel em andamento',
            $event->panel_sync_error === 'panel_configuration_incomplete' => 'Integração do painel central não configurada',
            $event->panel_sync_status === 'failed' => 'Falha na sincronização com painel central',
            $event->panel_sync_status === 'pending' => 'Aguardando sincronização com painel central',
            default => null,
        });
        return $event;
    }
}
