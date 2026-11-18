@extends('layouts.app', ['title' => 'Assinatura | RMC Flow Comércio Cloud'])
@section('content')
<section class="card narrow">
    <p class="muted" style="margin-bottom:8px">ASSINATURA DA EMPRESA</p>
    <h1 class="title">{{ $company->trade_name }}</h1>
    @if ($paymentNotice)
        <div class="alert {{ $paymentNotice['type'] === 'error' ? 'alert-danger' : ($paymentNotice['type'] === 'warning' ? 'alert-warming' : 'alert-success') }}" role="status">{{ $paymentNotice['text'] }}</div>
    @endif
    @error('checkout')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
    @if (session('plan_notice'))<div class="alert alert-success" role="status">{{ session('plan_notice') }}</div>@endif
    @if (session('panel_sync_notice'))
        <div class="alert alert-{{ session('panel_sync_notice_type', 'warming') }}" role="status">{{ session('panel_sync_notice') }}</div>
    @endif
    @if ($active)
        <p class="muted">Assinatura ativa até {{ $subscription->current_period_end->format('d/m/Y') }}.</p>
        <div class="alert alert-warming">Plano: <strong>{{ $subscription->plan->name }}</strong> · Até {{ $subscription->plan->limits['users'] ?? 'ilimitados' }} usuários · Computadores ilimitados.</div>
        @php($scheduledPlanChange = ($subscription->metadata ?? [])['scheduled_plan_change'] ?? null)
        @if ($scheduledPlanChange)
            <div class="alert alert-warming">Troca agendada para {{ $scheduledPlanChange['plan_code'] === 'comercio-cloud-essencial' ? 'Essencial' : $scheduledPlanChange['plan_code'] }} em {{ \Illuminate\Support\Carbon::parse($scheduledPlanChange['effective_at'])->format('d/m/Y') }}.</div>
            @if ($canManagePlan)<form method="post" action="{{ route('subscription.downgrade.cancel') }}">@csrf @method('DELETE')<button class="btn secondary" type="submit">Cancelar troca agendada</button></form>@endif
        @elseif ($canManagePlan && $subscription->plan->code === 'comercio-cloud-equipe')
            <p class="muted">O plano Essencial custa R$ 80,00 por ano na renovação e inclui até 3 usuários.</p>
            <form method="post" action="{{ route('subscription.downgrade') }}">@csrf<button class="btn secondary" type="submit">Agendar mudança para Essencial</button></form>
        @elseif ($canManagePlan && $subscription->plan->code === 'comercio-cloud-essencial' && $active && $canCheckout)
            @if ($upgradeQuoteCents)<p class="muted">Upgrade para até 10 usuários. Valor proporcional atual: <strong>R$ {{ number_format($upgradeQuoteCents / 100, 2, ',', '.') }}</strong>. O valor final será exibido no checkout antes da confirmação.</p><form method="post" action="{{ route('subscription.checkout') }}">@csrf<input type="hidden" name="plan_code" value="comercio-cloud-equipe"><button class="btn" type="submit">Continuar para o upgrade</button></form>@else<p class="muted">Não há valor proporcional a cobrar neste momento. Faça a troca de plano na renovação.</p>@endif
        @endif
    @elseif ($awaitingActivation)
        <p class="muted">O pagamento foi confirmado e aguarda a ativa&ccedil;&atilde;o da assinatura. N&atilde;o &eacute; necess&aacute;rio iniciar outra contrata&ccedil;&atilde;o.</p>
    @else
        <p class="muted">Ainda não há uma assinatura ativa para esta empresa.</p>
        @foreach($checkoutPlans as $plan)<div class="company" style="display:block;margin:14px 0"><h2 style="margin-top:0">{{ $plan->name }}</h2><p class="muted">Até {{ $plan->limits['users'] ?? 'ilimitados' }} usuários · Computadores ilimitados.</p><p>Primeira compra: <strong>R$ {{ number_format($plan->initial_price_cents / 100, 2, ',', '.') }}</strong><br>Renovação anual: <strong>R$ {{ number_format($plan->renewal_price_cents / 100, 2, ',', '.') }}</strong></p>@if($canCheckout)<form method="post" action="{{ route('subscription.checkout') }}">@csrf<input type="hidden" name="plan_code" value="{{ $plan->code }}"><button class="btn" type="submit">{{ $hasPriorPayment ? 'Renovar por R$ '.number_format($plan->renewal_price_cents / 100, 2, ',', '.') : 'Contratar por R$ '.number_format($plan->initial_price_cents / 100, 2, ',', '.') }}</button></form>@endif</div>@endforeach
        @if (!$canCheckout)<p class="muted">A contratação pelo Mercado Pago ficará disponível após cadastrar os planos no painel central e configurar as credenciais.</p>@endif
        @if ($subscription)<p><strong>Status atual:</strong> {{ $subscription->status }}</p>@endif
        @if ($canManagePlan && $hasPriorPayment)<p><a class="btn secondary" href="{{ route('company.users.index') }}">Gerenciar usuários antes de renovar</a></p>@endif
    @endif
    @if ($paymentEvents->isNotEmpty())
        <h2>Histórico de pagamentos</h2>
        <ul style="padding-left:20px">
            @foreach ($paymentEvents as $event)
                <li style="margin:12px 0">
                    <strong>{{ $event->billing_stage === 'renewal' ? 'Renovação' : ($event->billing_stage === 'initial' ? 'Primeira compra' : ($event->billing_stage === 'upgrade' ? 'Upgrade de plano' : 'Pagamento')) }}</strong>
                    <span class="muted">· {{ $event->received_at->format('d/m/Y H:i') }}</span><br>
                    @if (in_array($event->status, ['processed', 'awaiting_billing_policy', 'observed'], true))
                        {{ $event->status === 'observed' ? 'Valor informado' : 'Valor confirmado' }}: R$ {{ number_format(($event->paid_amount_cents ?? 0) / 100, 2, ',', '.') }} ·
                    @endif
                    <strong>{{ $event->display_status }}</strong>
                    @if ($event->display_detail)<br><span class="muted" style="margin:0">{{ $event->display_detail }}</span>@endif
                    @if ($event->display_panel_sync)<br><span class="muted" style="margin:0">{{ $event->display_panel_sync }}</span>@endif
                </li>
            @endforeach
        </ul>
    @endif
    @if ($panelSyncEvent)
        <div class="alert alert-warming">
            @if ($panelSyncConfigurationMissing)
                O pagamento está confirmado e a assinatura está aplicada neste sistema. Falta configurar a integração com o painel central para enviar a atualização.
            @elseif ($panelSyncEvent->panel_sync_status === 'syncing')
                Sincroniza&ccedil;&atilde;o com o painel central em andamento.
            @else
                O pagamento est&aacute; confirmado, mas o painel central ainda n&atilde;o recebeu a atualiza&ccedil;&atilde;o.
            @endif
            Tentativas realizadas: {{ $panelSyncEvent->panel_sync_attempts }}.
        </div>
        @if ($canRetryPanelSync && $panelRetryAvailable)
            @if ($panelSyncConfigurationMissing)<p class="muted">Configure a integração e tente novamente.</p>@endif
            <form method="post" action="{{ route('subscription.panel-sync.retry') }}">@csrf<button class="btn secondary" type="submit">Tentar sincroniza&ccedil;&atilde;o novamente</button></form>
        @endif
    @endif
    @if ($lastPanelSync)
        <p class="muted">&Uacute;ltima sincroniza&ccedil;&atilde;o com o painel central: {{ $lastPanelSync->panel_synced_at->format('d/m/Y H:i') }}.</p>
    @endif
    <div class="actions"><a class="btn secondary" href="{{ route('companies.index') }}">Trocar empresa</a><form method="post" action="{{ route('logout') }}">@csrf<button class="btn" type="submit">Sair</button></form></div>
</section>
@endsection
