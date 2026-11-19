@extends('layouts.app', ['title' => 'Venda #'.$sale->sale_number.' | RMC Flow Comércio Cloud'])
@section('content')
<section class="card">
    <div style="max-width:950px;margin:0 auto 22px;border-bottom:2px solid #102c4d;padding-bottom:16px">
        <h2 style="margin:0 0 8px">{{ $company->trade_name }}</h2>
        @if ($company->legal_name)<div>{{ $company->legal_name }}</div>@endif
        @if ($company->document)<div>Documento: {{ $company->document }}</div>@endif
        @if ($company->phone)<div>Telefone: {{ $company->phone }}</div>@endif
        @if ($addressText !== '')<div>{{ $addressText }}</div>@endif
    </div>
    @if (session('sale_notice'))<div class="alert alert-success">{{ session('sale_notice') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap"><div><p class="muted">VENDA</p><h1 class="title">Venda #{{ $sale->sale_number }} @if($sale->status === 'cancelled')<span style="color:#b42318">(Cancelada)</span>@endif</h1><p class="muted">{{ $sale->sold_at->format('d/m/Y H:i') }}</p></div><div class="actions"><a class="btn secondary" href="{{ route('sales.index') }}">Voltar às vendas</a><a class="btn" href="{{ route('sales.create') }}">Nova venda</a></div></div>
    <div style="margin:22px 0">
        <strong>Cliente</strong>
        <div>{{ $sale->customer?->name ?? 'Consumidor' }}</div>
        <?php if ($sale->customer?->document) { ?><div>{{ $sale->customer->document }}</div><?php } ?>
        <?php if ($sale->customer?->phone) { ?><div>{{ $sale->customer->phone }}</div><?php } ?>
        <?php if ($sale->customer?->email) { ?><div>{{ $sale->customer->email }}</div><?php } ?>
    </div>
    @if ($sale->status === 'cancelled')<div class="alert alert-warming">Cancelada em {{ $sale->cancelled_at?->format('d/m/Y H:i') }}. Motivo: {{ $sale->cancellation_reason }}. Solicitante: {{ $sale->cancelledBy?->name ?? 'Sistema' }}. Autorizada por: {{ $sale->cancellationApprover?->name ?? '—' }}.</div>@endif
    <h2>Itens</h2><div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;text-align:left"><thead><tr><th style="padding:8px">Produto</th><th style="padding:8px">Quantidade</th><th style="padding:8px">Preço unitário</th><th style="padding:8px">Total</th></tr></thead><tbody>@foreach ($sale->items as $item)<tr><td style="padding:8px">{{ $item->product_name }}</td><td style="padding:8px">{{ $item->quantity }}</td><td style="padding:8px">R$ {{ number_format($item->unit_price_cents / 100, 2, ',', '.') }}</td><td style="padding:8px">R$ {{ number_format($item->line_total_cents / 100, 2, ',', '.') }}</td></tr>@endforeach</tbody></table></div>
    <p style="text-align:right">Subtotal: R$ {{ number_format($sale->subtotal_cents / 100, 2, ',', '.') }}<br>Desconto: R$ {{ number_format($sale->discount_cents / 100, 2, ',', '.') }}<br><strong>Total: R$ {{ number_format($sale->total_cents / 100, 2, ',', '.') }}</strong><br>Recebido: R$ {{ number_format($sale->paid_cents / 100, 2, ',', '.') }} · Em aberto: R$ {{ number_format($sale->balance_cents / 100, 2, ',', '.') }}</p>
    <h2>Pagamentos registrados</h2>@forelse ($sale->payments as $payment)<p>{{ ['cash'=>'Dinheiro','pix'=>'PIX','debit_card'=>'Débito','credit_card'=>'Crédito','bank_transfer'=>'Transferência','other'=>'Outro'][$payment->payment_method] ?? $payment->payment_method }} — R$ {{ number_format($payment->amount_cents / 100, 2, ',', '.') }} · {{ $payment->paid_at->format('d/m/Y H:i') }}</p>@empty<p class="muted">Nenhum recebimento no ato.</p>@endforelse
    <h2>Contas a receber</h2>@forelse ($sale->receivables as $receivable)<p>{{ $receivable->installment_number }}ª parcela - vencimento {{ $receivable->due_date->format('d/m/Y') }} - R$ {{ number_format($receivable->amount_cents / 100, 2, ',', '.') }} · Recebido R$ {{ number_format($receivable->paid_cents / 100, 2, ',', '.') }} · Cancelado R$ {{ number_format($receivable->cancelled_balance_cents / 100, 2, ',', '.') }} · {{ $receivable->status === 'pending' ? 'Pendente' : $receivable->status }}</p>@empty<p class="muted">Sem saldo parcelado.</p>@endforelse
    @if ($sale->status === 'completed')<hr style="border:0;border-top:1px solid #e1e8f0;margin:24px 0"><h2>Cancelar venda</h2><p class="muted">O estoque será estornado e apenas os saldos a receber ainda não pagos serão cancelados. Valores já recebidos permanecem registrados. A operação exige autorização administrativa.</p><form method="post" action="{{ route('sales.cancel', $sale) }}" class="grid" style="max-width:850px">@csrf @method('PATCH')<div class="field" style="grid-column:1/-1"><label for="reason">Motivo</label><input id="reason" name="reason" maxlength="180" minlength="5" required value="{{ old('reason') }}"></div>@if($role === 'Administrador')<div class="field"><label for="current_password">Confirme sua senha de administrador</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>@else<div class="field"><label for="approval_email_cancel">E-mail do administrador</label><input id="approval_email_cancel" name="approval_email" type="email" autocomplete="username" required></div><div class="field"><label for="approval_password_cancel">Senha do administrador</label><input id="approval_password_cancel" name="approval_password" type="password" autocomplete="new-password" required></div>@endif<div class="field"><label aria-hidden="true">&nbsp;</label><button class="btn secondary" type="submit" onclick="return confirm('Confirmar o cancelamento desta venda?')">Solicitar cancelamento</button></div></form>@endif
    @if ($sale->status === 'cancelled')
        <h2>Devoluções registradas</h2>
        @forelse ($sale->refunds as $refund)
            <p>{{ $refund->refunded_at->format('d/m/Y H:i') }} · {{ ['cash'=>'Dinheiro','pix'=>'PIX','debit_card'=>'Débito','credit_card'=>'Crédito','bank_transfer'=>'Transferência','other'=>'Outro'][$refund->payment_method] ?? $refund->payment_method }} · R$ {{ number_format($refund->amount_cents / 100, 2, ',', '.') }} · {{ $refund->reason }} · solicitado por {{ $refund->creator?->name ?? 'Sistema' }} · autorizado por {{ $refund->approver?->name ?? '—' }}</p>
        @empty
            <p class="muted">Nenhuma devolução financeira registrada.</p>
        @endforelse
        <h2>Registrar devolução</h2>
        <p class="muted">A devolução deve usar uma forma original de pagamento e não pode ultrapassar o saldo recebido por ela. Em dinheiro, a saída será registrada no caixa aberto da empresa no momento da devolução; caixa fechado não é alterado.</p>
        @if($refundOptions->isEmpty())<div class="alert alert-warming">Não há saldo recebido disponível para devolução.</div>@else
        <form method="post" action="{{ route('sales.refund', $sale) }}" class="grid" style="max-width:850px">@csrf
            <input type="hidden" name="request_key" value="{{ $refundRequestKey }}">
            <div class="field"><label for="refund_amount">Valor (R$)</label><input id="refund_amount" name="amount" type="number" min="0.01" step="0.01" required value="{{ old('amount') }}"></div>
            <div class="field"><label for="refund_method">Forma de devolução</label><select id="refund_method" name="payment_method" required style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit">@foreach($refundOptions as $method => $option)<option value="{{ $method }}" @selected(old('payment_method', $refundOptions->keys()->first()) === $method)>{{ $option['label'] }} — disponível R$ {{ number_format($option['remaining_cents'] / 100, 2, ',', '.') }}</option>@endforeach</select></div>
            <div class="field"><label for="refunded_at">Data e hora</label><input id="refunded_at" name="refunded_at" type="datetime-local" max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ now()->format('Y-m-d\TH:i') }}" required></div>
            <div class="field"><label for="refund_reference">Referência (opcional)</label><input id="refund_reference" name="reference" maxlength="120" value="{{ old('reference') }}"></div>
            <div class="field" style="grid-column:1/-1"><label for="refund_reason">Motivo</label><input id="refund_reason" name="reason" minlength="5" maxlength="180" required value="{{ old('reason') }}"></div>
            @if($role === 'Administrador')<div class="field" style="grid-column:1/-1"><label for="current_password_refund">Confirme sua senha de administrador</label><input id="current_password_refund" name="current_password" type="password" autocomplete="current-password" required></div>@else<div class="field"><label for="approval_email_refund">E-mail do administrador</label><input id="approval_email_refund" name="approval_email" type="email" autocomplete="username" required></div><div class="field"><label for="approval_password_refund">Senha do administrador</label><input id="approval_password_refund" name="approval_password" type="password" autocomplete="new-password" required></div>@endif
            <div><button class="btn" type="submit" onclick="return confirm('Registrar a devolução financeira?')">Registrar devolução</button></div>
        </form>
        @endif
    @endif
</section>
@endsection
