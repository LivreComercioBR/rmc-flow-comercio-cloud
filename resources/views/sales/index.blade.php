@extends('layouts.app', ['title' => 'PDV e Vendas | RMC Flow Comércio Cloud'])
@section('content')
<section class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap"><div><p class="muted">COMERCIAL</p><h1 class="title">PDV e Vendas</h1><p class="muted">Vendas registradas e recebíveis gerados.</p></div><div class="actions"><a class="btn" href="{{ route('sales.create') }}">Nova venda</a><a class="btn secondary" href="{{ route('financial.summary') }}">Resumo financeiro</a><a class="btn secondary" href="{{ route('cash.index') }}">Caixa</a></div></div>
    @if (session('sale_notice'))<div class="alert alert-success">{{ session('sale_notice') }}</div>@endif
    @if ($sales->isEmpty())<div class="alert alert-warming">Nenhuma venda registrada.</div>@else
    <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;text-align:left"><thead><tr><th style="padding:10px">Venda</th><th style="padding:10px">Data</th><th style="padding:10px">Cliente</th><th style="padding:10px">Total</th><th style="padding:10px">Recebido</th><th style="padding:10px">Em aberto</th><th style="padding:10px">Detalhes</th></tr></thead><tbody>@foreach ($sales as $sale)<tr><td style="padding:10px">#{{ $sale->sale_number }}</td><td style="padding:10px">{{ $sale->sold_at->format('d/m/Y H:i') }}</td><td style="padding:10px">{{ $sale->customer?->name ?? 'Consumidor' }}</td><td style="padding:10px">R$ {{ number_format($sale->total_cents / 100, 2, ',', '.') }}</td><td style="padding:10px">R$ {{ number_format($sale->paid_cents / 100, 2, ',', '.') }}</td><td style="padding:10px">R$ {{ number_format($sale->balance_cents / 100, 2, ',', '.') }}</td><td style="padding:10px"><a class="btn secondary" href="{{ route('sales.show', $sale) }}">Abrir</a></td></tr>@endforeach</tbody></table></div><div style="display:flex;justify-content:flex-end;gap:12px;margin-top:16px">{{ $sales->links() }}</div>
    @endif
</section>
@endsection
