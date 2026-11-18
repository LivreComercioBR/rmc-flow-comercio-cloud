@extends('layouts.app', ['title' => 'Clientes | RMC Flow Comércio Cloud'])
@section('content')
<section class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
        <div><p class="muted" style="margin-bottom:8px">CADASTROS</p><h1 class="title">Clientes</h1></div>
        <div class="actions"><a class="btn secondary" href="{{ route('dashboard') }}">Painel</a><a class="btn" href="{{ route('customers.create') }}">Novo cliente</a></div>
    </div>
    @if (session('customer_notice'))<div class="alert alert-success" role="status">{{ session('customer_notice') }}</div>@endif
    <form method="get" action="{{ route('customers.index') }}" class="actions" style="margin:12px 0 20px">
        <input aria-label="Pesquisar clientes" type="search" name="q" value="{{ $search }}" placeholder="Nome, documento, telefone ou e-mail" style="flex:1;min-width:220px;height:42px;border:1px solid #cbd5e1;border-radius:8px;padding:0 12px;font:inherit">
        <button class="btn" type="submit">Pesquisar</button>
        @if ($search !== '')<a class="btn secondary" href="{{ route('customers.index') }}">Limpar</a>@endif
    </form>
    @if ($customers->isEmpty())
        <div class="alert alert-warming">{{ $search !== '' ? 'Nenhum cliente corresponde à pesquisa.' : 'Ainda não há clientes cadastrados.' }}</div>
    @else
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;text-align:left">
                <thead><tr><th style="padding:10px;border-bottom:1px solid #dbe3ed">Nome</th><th style="padding:10px;border-bottom:1px solid #dbe3ed">Documento</th><th style="padding:10px;border-bottom:1px solid #dbe3ed">Contato</th><th style="padding:10px;border-bottom:1px solid #dbe3ed">Status</th><th style="padding:10px;border-bottom:1px solid #dbe3ed">Ações</th></tr></thead>
                <tbody>
                @foreach ($customers as $customer)
                    <tr>
                        <td style="padding:10px;border-bottom:1px solid #edf1f5"><strong>{{ $customer->name }}</strong><br><span class="muted">{{ $customer->address }}</span></td>
                        <td style="padding:10px;border-bottom:1px solid #edf1f5">{{ $customer->document ?: '—' }}</td>
                        <td style="padding:10px;border-bottom:1px solid #edf1f5">{{ $customer->phone }}<br>{{ $customer->email }}</td>
                        <td style="padding:10px;border-bottom:1px solid #edf1f5">{{ $customer->status === 'active' ? 'Ativo' : 'Inativo' }}</td>
                        <td style="padding:10px;border-bottom:1px solid #edf1f5;white-space:nowrap"><a class="btn secondary" href="{{ route('customers.show', $customer->id) }}">Histórico</a> <a class="btn secondary" href="{{ route('customers.edit', $customer->id) }}">Editar</a>
                            @if ($customer->status === 'active')<form method="post" action="{{ route('customers.deactivate', $customer->id) }}" style="display:inline" onsubmit="return confirm('Desativar este cliente? O histórico será preservado.')">@csrf @method('PATCH')<button class="btn secondary" type="submit">Desativar</button></form>@else<form method="post" action="{{ route('customers.activate', $customer->id) }}" style="display:inline">@csrf @method('PATCH')<button class="btn secondary" type="submit">Reativar</button></form>@endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:18px">
            <span class="muted">{{ $customers->firstItem() }}–{{ $customers->lastItem() }} de {{ $customers->total() }}</span>
            <div class="actions">@if ($customers->previousPageUrl())<a class="btn secondary" href="{{ $customers->previousPageUrl() }}">Anterior</a>@endif @if ($customers->nextPageUrl())<a class="btn secondary" href="{{ $customers->nextPageUrl() }}">Próxima</a>@endif</div>
        </div>
    @endif
</section>
@endsection
