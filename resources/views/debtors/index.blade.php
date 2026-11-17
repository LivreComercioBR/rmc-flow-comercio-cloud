@extends('layouts.app', ['title' => 'Devedores | RMC Flow Comércio Cloud'])
@section('content')
<section class="card">
    <p class="muted" style="margin-bottom:8px">FINANCEIRO</p>
    <h1 class="title">Devedores</h1>
    <p class="muted">Clientes com parcelas de contas a receber em aberto. O saldo reúne vendas e lançamentos financeiros vinculados ao cliente.</p>
    <form method="get" action="{{ route('debtors.index') }}" class="actions" style="margin-bottom:18px;flex-wrap:wrap">
        <input name="q" value="{{ $search }}" placeholder="Pesquisar por nome, documento ou telefone" style="min-height:42px;min-width:min(360px,100%);border:1px solid #cbd5e1;border-radius:8px;padding:0 12px;font:inherit">
        <button class="btn" type="submit">Pesquisar</button>
        @if($search !== '')<a class="btn secondary" href="{{ route('debtors.index') }}">Limpar</a>@endif
    </form>
    @if($debtors->isEmpty())
        <p class="muted">Nenhum cliente com saldo em aberto{{ $search !== '' ? ' corresponde à pesquisa.' : '.' }}</p>
    @else
        <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;text-align:left">
            <thead><tr><th style="padding:10px;border-bottom:1px solid #dbe3ed">Cliente</th><th style="padding:10px;border-bottom:1px solid #dbe3ed">Telefone</th><th style="padding:10px;border-bottom:1px solid #dbe3ed">Saldo em aberto</th><th style="padding:10px;border-bottom:1px solid #dbe3ed">Parcelas vencidas</th><th style="padding:10px;border-bottom:1px solid #dbe3ed">Ação</th></tr></thead>
            <tbody>@foreach($debtors as $debtor)
                <tr><td style="padding:10px;border-bottom:1px solid #edf1f5"><strong>{{ $debtor->name }}</strong><br><span class="muted" style="margin:0">{{ $debtor->document ?: 'Sem documento' }}</span></td>
                    <td style="padding:10px;border-bottom:1px solid #edf1f5">{{ $debtor->phone ?: '—' }}</td>
                    <td style="padding:10px;border-bottom:1px solid #edf1f5">R$ {{ number_format((int) $debtor->open_balance_cents / 100, 2, ',', '.') }}</td>
                    <td style="padding:10px;border-bottom:1px solid #edf1f5">{{ number_format((int) $debtor->overdue_count, 0, ',', '.') }}</td>
                    <td style="padding:10px;border-bottom:1px solid #edf1f5"><a class="btn secondary" href="{{ route('customers.show', $debtor->id) }}">Ver histórico</a></td></tr>
            @endforeach</tbody>
        </table></div>
        <div style="margin-top:18px">{{ $debtors->links() }}</div>
    @endif
    <div class="actions" style="margin-top:18px"><a class="btn secondary" href="{{ route('receivables.index', ['status' => 'open']) }}">Contas a receber</a><a class="btn secondary" href="{{ route('dashboard') }}">Voltar ao painel</a></div>
</section>
@endsection
