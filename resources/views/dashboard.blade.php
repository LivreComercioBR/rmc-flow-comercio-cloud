@extends('layouts.app', ['title' => 'Painel | RMC Flow Comércio Cloud'])
@section('content')
<section class="card dashboard-card">
    <p class="muted" style="margin-bottom:8px">PAINEL DA EMPRESA</p>
    <h1 class="title">{{ $company->trade_name }}</h1>
    <p class="muted">Resumo da operação da empresa.</p>
      @if($canAccessServiceCatalog)<div class="actions"><a class="btn secondary" href="{{ route('services.index') }}">Catálogo de serviços</a><a class="btn secondary" href="{{ route('services.assets.index') }}">Veículos / equipamentos</a><a class="btn secondary" href="{{ route('services.appointments.index') }}">Agenda</a><a class="btn secondary" href="{{ route('services.quotes.index') }}">Orçamentos</a><a class="btn secondary" href="{{ route('services.work-orders.index') }}">Ordens de serviço</a></div>@endif
      @if($role === 'Mecânico')@if($servicesEnabled)<div class="alert alert-warming">Catálogo, agenda, orçamentos e ordens de serviço estão disponíveis conforme as permissões da equipe.</div>@else<div class="alert alert-warming">O módulo de serviços está desativado para esta empresa.</div>@endif @else
    <form method="post" action="{{ route('dashboard.values.toggle') }}" style="margin-top:14px">@csrf<button class="btn secondary" type="submit">{{ $hideValues ? 'Exibir valores' : 'Ocultar valores' }}</button></form><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin:22px 0">
        <a class="company" href="{{ route('customers.index') }}" style="display:block;text-decoration:none;color:inherit;margin:0"><span class="muted" style="display:block;margin:0 0 8px">Clientes ativos</span><strong style="font-size:26px;color:#102c4d">{{ number_format($customerCount, 0, ',', '.') }}</strong><span style="display:block;color:#0f3cff;margin-top:8px">Abrir cadastro de clientes →</span></a>
        <a class="company" href="{{ route('products.index') }}" style="display:block;text-decoration:none;color:inherit;margin:0"><span class="muted" style="display:block;margin:0 0 8px">Produtos / peças ativos</span><strong style="font-size:26px;color:#102c4d">{{ number_format($productCount, 0, ',', '.') }}</strong><span style="display:block;color:#0f3cff;margin-top:8px">Abrir catálogo →</span></a>
        <a class="company" href="{{ route('products.index', ['status' => 'low_stock']) }}" style="display:block;text-decoration:none;color:inherit;margin:0"><span class="muted" style="display:block;margin:0 0 8px">Estoque baixo</span><strong style="font-size:26px;color:#102c4d">{{ number_format($lowStockCount, 0, ',', '.') }}</strong><span style="display:block;color:#0f3cff;margin-top:8px">Ver alertas de estoque →</span></a>
        @if($role === 'Administrador')<a class="company" href="{{ route('payables.index', ['status' => 'open']) }}" style="display:block;text-decoration:none;color:inherit;margin:0"><span class="muted" style="display:block;margin:0 0 8px">Contas a pagar em aberto</span><strong style="font-size:26px;color:#102c4d">{{ $hideValues ? '••••••' : 'R$ '.number_format($openPayableCents / 100, 2, ',', '.') }}</strong><span style="display:block;color:#0f3cff;margin-top:8px">{{ number_format($overduePayables, 0, ',', '.') }} vencida(s) · abrir financeiro →</span></a>
        <a class="company" href="{{ route('receivables.index', ['status' => 'open']) }}" style="display:block;text-decoration:none;color:inherit;margin:0"><span class="muted" style="display:block;margin:0 0 8px">Contas a receber em aberto</span><strong style="font-size:26px;color:#102c4d">{{ $hideValues ? '••••••' : 'R$ '.number_format($openReceivableCents / 100, 2, ',', '.') }}</strong><span style="display:block;color:#0f3cff;margin-top:8px">{{ number_format($overdueReceivables, 0, ',', '.') }} vencida(s) · abrir financeiro →</span></a>@endif
        <a class="company" href="{{ route('cash.index') }}" style="display:block;text-decoration:none;color:inherit;margin:0"><span class="muted" style="display:block;margin:0 0 8px">Dinheiro no caixa</span><strong style="font-size:26px;color:#102c4d">{{ $openCashSession ? ($hideValues ? '••••••' : 'R$ '.number_format($cashBalanceCents / 100, 2, ',', '.')) : 'Fechado' }}</strong><span style="display:block;color:#0f3cff;margin-top:8px">{{ $openCashSession ? 'Abrir movimentações →' : 'Abrir caixa →' }}</span></a>
    </div>
    <div class="dashboard-nav"><a class="btn" href="{{ route('customers.index') }}">Clientes</a><a class="btn" href="{{ route('products.index') }}">Produtos / peças</a><a class="btn" href="{{ route('sales.index') }}">PDV / Vendas</a><a class="btn" href="{{ route('cash.index') }}">Caixa</a>@if($role === 'Administrador')<a class="btn" href="{{ route('categories.index') }}">Categorias</a><a class="btn" href="{{ route('receivables.index') }}">Contas a receber</a><a class="btn" href="{{ route('payables.index') }}">Contas a pagar</a><a class="btn" href="{{ route('financial.summary') }}">Resumo financeiro</a><a class="btn" href="{{ route('reports.index') }}">Relatorios</a><a class="btn" href="{{ route('debtors.index') }}">Devedores</a><a class="btn" href="{{ route('suppliers.index') }}">Fornecedores</a><a class="btn" href="{{ route('purchases.index') }}">Compras</a><a class="btn secondary" href="{{ route('company.settings.edit') }}">Configurações</a>@endif<a class="btn secondary" href="{{ route('companies.index') }}">Trocar empresa</a><form method="post" action="{{ route('logout') }}">@csrf<button class="btn secondary" type="submit">Sair</button></form></div>@endif
</section>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:14px;margin-top:18px">
    <details class="company" style="display:block;margin:0;padding:0;overflow:hidden;background:#0c3934;border-color:#17695e;color:#eafffa">
        <summary style="cursor:pointer;list-style-position:inside;padding:16px 18px;font-weight:700">
            Minha assinatura
            <span style="display:block;font-size:13px;font-weight:400;color:#a8d6cd;margin-top:5px">{{ $subscription?->plan?->name ?? 'Consultar plano e validade' }} · {{ $subscriptionActive ? 'Ativa' : ($subscription?->current_period_end?->isPast() ? 'Vencida' : 'Ver situação') }}</span>
        </summary>
        <div style="padding:0 18px 18px">
            @if($subscription)
                <p style="margin:6px 0">Plano: <strong>{{ $subscription->plan?->name ?? 'N&atilde;o identificado' }}</strong></p>
                <p style="margin:6px 0">Situa&ccedil;&atilde;o: <strong>{{ $subscriptionActive ? 'Ativa' : ($subscription->current_period_end?->isPast() ? 'Vencida' : ucfirst(str_replace('_', ' ', $subscription->status))) }}</strong></p>
                @if($subscription->current_period_end)<p style="margin:6px 0">Vencimento: <strong>{{ $subscription->current_period_end->format('d/m/Y') }}</strong></p>@endif
                @php($scheduledPlanChange = ($subscription->metadata ?? [])['scheduled_plan_change'] ?? null)
                @if($scheduledPlanChange)<p style="margin:8px 0">Pr&oacute;xima troca: {{ $scheduledPlanChange['plan_code'] === 'comercio-cloud-essencial' ? 'Essencial' : $scheduledPlanChange['plan_code'] }} em {{ \Illuminate\Support\Carbon::parse($scheduledPlanChange['effective_at'])->format('d/m/Y') }}.</p>@endif
            @else
                <p>Nenhuma assinatura encontrada para esta empresa.</p>
            @endif
            <div class="actions" style="flex-wrap:wrap;margin-top:14px">
                <a class="btn secondary" href="{{ route('subscription.status') }}">Ver dados e op&ccedil;&otilde;es</a>
                @if($upgradeAvailable)<form method="post" action="{{ route('subscription.checkout') }}">@csrf<input type="hidden" name="plan_code" value="comercio-cloud-equipe"><button class="btn" type="submit">Upgrade para Equipe</button></form>@endif
                @if($renewalAvailable)<form method="post" action="{{ route('subscription.checkout') }}">@csrf<input type="hidden" name="plan_code" value="{{ $renewalPlan->code }}"><button class="btn" type="submit">Renovar</button></form>@endif
            </div>
            @if($subscription && ! $canManageSubscription)<p style="margin:10px 0 0;color:#a8d6cd">Para alterar ou renovar o plano, solicite ao administrador da empresa.</p>@endif
        </div>
    </details>
    <details class="company" style="display:block;margin:0;padding:0;overflow:hidden;background:#0c3934;border-color:#17695e;color:#eafffa">
        <summary style="cursor:pointer;list-style-position:inside;padding:16px 18px;font-weight:700">
            Precisa de suporte?
            <span style="display:block;font-size:13px;font-weight:400;color:#a8d6cd;margin-top:5px">E-mail e WhatsApp da RMC Sites</span>
        </summary>
        <div style="padding:0 18px 18px">
            <p style="margin:0 0 12px;color:#a8d6cd">Fale com a equipe da RMC Sites se encontrar um problema ou tiver d&uacute;vidas.</p>
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
                @if($supportEmail)<a href="mailto:{{ $supportEmail }}" style="color:#eafffa">{{ $supportEmail }}</a>@endif
                @if($supportWhatsapp)<a class="btn" href="https://wa.me/{{ preg_replace('/\D+/', '', $supportWhatsapp) }}" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:8px;background:#00c875;border-color:#00e58a;color:#052b24">
                    <img src="{{ asset('images/whatsapp-icon.svg') }}" alt="" width="22" height="22" style="display:block">
                    <span>Falar pelo WhatsApp</span>
                </a>@endif
            </div>
        </div>
    </details>
</div>
@endsection
