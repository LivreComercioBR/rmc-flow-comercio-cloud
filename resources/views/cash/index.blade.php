@extends('layouts.app', ['title' => 'Caixa | RMC Flow Comércio Cloud'])
@section('content')
<section class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap"><div><p class="muted" style="margin-bottom:8px">FINANCEIRO</p><h1 class="title">Caixa</h1></div><div class="actions"><a class="btn secondary" href="{{ route('dashboard') }}">Painel</a>@if($role === 'Administrador')<a class="btn secondary" href="{{ route('payables.index') }}">Contas a pagar</a>@endif</div></div>
    @if (session('cash_notice'))<div class="alert alert-warning" role="status">{{ session('cash_notice') }}</div>@endif
    @if ($errors->any())<div class="alert" role="alert">{{ $errors->first() }}</div>@endif

    @if (! $openSession)
        <div class="alert alert-warning">Não há caixa aberto para esta empresa.</div>
        <form method="post" action="{{ route('cash.open') }}" class="grid" style="max-width:620px">@csrf
            <div class="field"><label for="opening_amount">Saldo inicial em dinheiro (R$)</label><input id="opening_amount" name="opening_amount" type="number" min="0" max="42949672.95" step="0.01" required value="{{ old('opening_amount', '0.00') }}">@error('opening_amount')<div class="error">{{ $message }}</div>@enderror</div>
            <div><button class="btn" type="submit">Abrir caixa</button></div>
        </form>
    @else
        @php($totalInCents = $openSession->movements->where('direction', 'in')->sum('amount_cents'))
        @php($totalOutCents = $openSession->movements->where('direction', 'out')->sum('amount_cents'))
        <p class="muted">Aberto em {{ $openSession->opened_at->format('d/m/Y H:i') }} por {{ $openSession->opener?->name ?? 'Sistema' }}.</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin:18px 0">
            <div class="company" style="display:block;margin:0"><span class="muted" style="display:block;margin:0 0 8px">Saldo de abertura</span><strong style="font-size:22px">R$ {{ number_format($openSession->opening_amount_cents / 100, 2, ',', '.') }}</strong></div>
            <div class="company" style="display:block;margin:0"><span class="muted" style="display:block;margin:0 0 8px">Entradas</span><strong style="font-size:22px">R$ {{ number_format($totalInCents / 100, 2, ',', '.') }}</strong></div>
            <div class="company" style="display:block;margin:0"><span class="muted" style="display:block;margin:0 0 8px">Saídas</span><strong style="font-size:22px">R$ {{ number_format($totalOutCents / 100, 2, ',', '.') }}</strong></div>
            <div class="company" style="display:block;margin:0"><span class="muted" style="display:block;margin:0 0 8px">Dinheiro esperado</span><strong style="font-size:22px">R$ {{ number_format($expectedCents / 100, 2, ',', '.') }}</strong></div>
        </div>

        <h2>Registrar movimentação</h2>
        <form method="post" action="{{ route('cash.movement', $openSession->id) }}" class="grid" style="margin-bottom:24px">@csrf<input type="hidden" name="request_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
            <div class="field"><label for="movement_type">Tipo de movimentação</label><select id="movement_type" name="movement_type" required style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit"><option value="entry">Entrada de dinheiro</option><option value="supply">Suprimento (colocar dinheiro no caixa)</option><option value="expense">Saída de dinheiro</option><option value="withdrawal">Sangria (retirar dinheiro do caixa)</option></select>@error('movement_type')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="amount">Valor (R$)</label><input id="amount" name="amount" type="number" min="0.01" max="42949672.95" step="0.01" required value="{{ old('amount') }}">@error('amount')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="description">Descrição / motivo</label><input id="description" name="description" maxlength="180" required value="{{ old('description') }}">@error('description')<div class="error">{{ $message }}</div>@enderror</div>
            <div><button class="btn" type="submit">Registrar</button></div>
        </form>

        <h2>Movimentações deste caixa</h2>
        @if ($openSession->movements->isEmpty())<div class="alert alert-warning">Nenhuma movimentação após a abertura.</div>@else
        <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;text-align:left"><thead><tr><th style="padding:10px;border-bottom:1px solid #dbe3ed">Data</th><th style="padding:10px;border-bottom:1px solid #dbe3ed">Tipo</th><th style="padding:10px;border-bottom:1px solid #dbe3ed">Descrição</th><th style="padding:10px;border-bottom:1px solid #dbe3ed">Valor</th><th style="padding:10px;border-bottom:1px solid #dbe3ed">Responsável e autorização</th></tr></thead><tbody>@foreach ($openSession->movements as $movement)@php($refund = $movement->source_type === 'sale_refund' ? $refundsById->get($movement->source_id) : null)@php($workOrderRefund = $movement->source_type === 'service_work_order_refund' ? $workOrderRefundsById->get($movement->source_id) : null)@php($refundRecord = $refund ?? $workOrderRefund)<tr><td style="padding:10px;border-bottom:1px solid #edf1f5">{{ $movement->created_at->format('d/m/Y H:i') }}</td><td style="padding:10px;border-bottom:1px solid #edf1f5">{{ ['cash_in'=>'Entrada','sale_payment'=>'Recebimento de venda','receivable_payment'=>'Recebimento de conta','supply'=>'Suprimento','cash_out'=>'Saída','withdrawal'=>'Sangria','payable_payment'=>'Pagamento de conta','service_work_order_refund' => 'Devolução de OS', 'sale_refund' => 'Devolução de venda'][$movement->movement_type] ?? $movement->movement_type }}</td><td style="padding:10px;border-bottom:1px solid #edf1f5">{{ $movement->description }}@if($refundRecord)<br><span class="muted" style="margin:0">Motivo: {{ $refundRecord->reason }}</span>@endif</td><td style="padding:10px;border-bottom:1px solid #edf1f5;color:{{ $movement->direction === 'in' ? '#087443' : '#b42318' }}">{{ $movement->direction === 'in' ? '+' : '-' }} R$ {{ number_format($movement->amount_cents / 100, 2, ',', '.') }}</td><td style="padding:10px;border-bottom:1px solid #edf1f5">@if($refundRecord)Solicitado por {{ $refundRecord->creator?->name ?? 'Sistema' }}<br>Autorizado por {{ $refundRecord->approver?->name ?? '—' }}@else{{ $movement->creator?->name ?? 'Sistema' }}@endif</td></tr>@endforeach</tbody></table></div>
        @endif

        <hr style="border:0;border-top:1px solid #e1e8f0;margin:24px 0">
        <h2>Fechar caixa</h2><p class="muted">Conte o dinheiro físico e informe o total. O sistema compara com o saldo esperado e registra a diferença.</p>
        <form method="post" action="{{ route('cash.close', $openSession->id) }}" class="grid" style="max-width:760px">@csrf
            <div class="field"><label for="counted_amount">Dinheiro contado (R$)</label><input id="counted_amount" name="counted_amount" type="number" min="0" max="42949672.95" step="0.01" required value="{{ old('counted_amount', number_format($expectedCents / 100, 2, '.', '')) }}">@error('counted_amount')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="closing_note">Observação sobre diferença (opcional)</label><input id="closing_note" name="closing_note" maxlength="180" value="{{ old('closing_note') }}"></div>
            <div><button class="btn secondary" type="submit" onclick="return confirm('Fechar o caixa e registrar o valor contado?')">Fechar caixa</button></div>
        </form>
    @endif

    <h2 style="margin-top:30px">Caixas fechados</h2>
    @if ($history->isEmpty())<p class="muted">Ainda não há fechamentos.</p>@else
    <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;text-align:left"><thead><tr><th style="padding:10px;border-bottom:1px solid #dbe3ed">Abertura</th><th style="padding:10px;border-bottom:1px solid #dbe3ed">Fechamento</th><th style="padding:10px;border-bottom:1px solid #dbe3ed">Esperado</th><th style="padding:10px;border-bottom:1px solid #dbe3ed">Contado</th><th style="padding:10px;border-bottom:1px solid #dbe3ed">Diferença</th><th style="padding:10px;border-bottom:1px solid #dbe3ed">Detalhes</th></tr></thead><tbody>@foreach ($history as $closed)<tr><td style="padding:10px;border-bottom:1px solid #edf1f5">{{ $closed->opened_at->format('d/m/Y H:i') }}</td><td style="padding:10px;border-bottom:1px solid #edf1f5">{{ $closed->closed_at?->format('d/m/Y H:i') }}</td><td style="padding:10px;border-bottom:1px solid #edf1f5">R$ {{ number_format($closed->expected_closing_cents / 100, 2, ',', '.') }}</td><td style="padding:10px;border-bottom:1px solid #edf1f5">R$ {{ number_format($closed->counted_closing_cents / 100, 2, ',', '.') }}</td><td style="padding:10px;border-bottom:1px solid #edf1f5">{{ ((int)$closed->difference_cents) >= 0 ? '+' : '−' }} R$ {{ number_format(abs((int)$closed->difference_cents) / 100, 2, ',', '.') }}</td><td style="padding:10px;border-bottom:1px solid #edf1f5"><a class="btn secondary" href="{{ route('cash.show', $closed->id) }}">Abrir</a></td></tr>@endforeach</tbody></table></div>
    <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:16px">@if ($history->previousPageUrl())<a class="btn secondary" href="{{ $history->previousPageUrl() }}">Anterior</a>@endif @if ($history->nextPageUrl())<a class="btn secondary" href="{{ $history->nextPageUrl() }}">Próxima</a>@endif</div>
    @endif
</section>
@endsection
