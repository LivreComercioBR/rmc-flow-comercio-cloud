@extends('layouts.app', ['title' => 'Catálogo de serviços | RMC Flow Comércio Cloud'])
@section('content')
<section class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap">
        <div><p class="muted" style="margin-bottom:8px">MÓDULO DE SERVIÇOS</p><h1 class="title">Catálogo de serviços</h1><p class="muted">Cadastre serviços com descrição e preço para usar nos próximos fluxos de atendimento.</p></div>
        <div class="actions"><a class="btn secondary" href="{{ route('services.appointments.index') }}">Agenda</a><a class="btn secondary" href="{{ route('services.quotes.index') }}">Orçamentos</a><a class="btn secondary" href="{{ route('dashboard') }}">Painel</a></div>
    </div>
    @if (session('service_notice'))<div class="alert alert-success" role="status">{{ session('service_notice') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

    @if ($role === 'Administrador')
    <details style="margin:18px 0"><summary class="btn" style="display:inline-flex">Adicionar serviço</summary>
        <form method="post" action="{{ route('services.store') }}" class="grid" style="max-width:820px;margin-top:16px">@csrf
            <div class="field"><label for="name">Nome do serviço</label><input id="name" name="name" maxlength="160" required value="{{ old('name') }}">@error('name')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="price">Preço (R$)</label><input id="price" name="price" type="number" min="0" max="99999999.99" step="0.01" required value="{{ old('price', '0.00') }}">@error('price')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field" style="grid-column:1/-1"><label for="description">Descrição (opcional)</label><textarea id="description" name="description" maxlength="2000" rows="3" style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:10px;font:inherit">{{ old('description') }}</textarea>@error('description')<div class="error">{{ $message }}</div>@enderror</div>
            <div><button class="btn" type="submit">Salvar serviço</button></div>
        </form>
    </details>
    @endif

    <form method="get" class="grid" style="max-width:760px;margin:20px 0">
        <div class="field"><label for="q">Pesquisar</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nome ou descrição"></div>
        <div class="field"><label for="status">Situação</label><select id="status" name="status" style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit"><option value="">Todos</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Ativos</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inativos</option></select></div>
        <div><button class="btn secondary" type="submit">Filtrar</button></div>
    </form>

    @if ($items->isEmpty())<p class="muted">Nenhum serviço encontrado no catálogo.</p>@else
    <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;text-align:left"><thead><tr><th style="padding:10px;border-bottom:1px solid #dbe3ed">Serviço</th><th style="padding:10px;border-bottom:1px solid #dbe3ed">Preço</th><th style="padding:10px;border-bottom:1px solid #dbe3ed">Situação</th>@if($role === 'Administrador')<th style="padding:10px;border-bottom:1px solid #dbe3ed">Ações</th>@endif</tr></thead><tbody>
    @foreach ($items as $item)
        <tr><td style="padding:10px;border-bottom:1px solid #edf1f5"><strong>{{ $item->name }}</strong>@if($item->description)<br><span class="muted" style="display:inline-block;margin:4px 0 0">{{ $item->description }}</span>@endif</td><td style="padding:10px;border-bottom:1px solid #edf1f5">R$ {{ number_format($item->price_cents / 100, 2, ',', '.') }}</td><td style="padding:10px;border-bottom:1px solid #edf1f5">{{ $item->status === 'active' ? 'Ativo' : 'Inativo' }}</td>
        @if($role === 'Administrador')<td style="padding:10px;border-bottom:1px solid #edf1f5"><details><summary class="btn secondary">Editar</summary><form method="post" action="{{ route('services.update', $item->id) }}" style="min-width:260px;margin-top:10px">@csrf @method('PUT')<div class="field"><label>Nome</label><input name="name" maxlength="160" required value="{{ $item->name }}"></div><div class="field"><label>Preço (R$)</label><input name="price" type="number" min="0" max="99999999.99" step="0.01" required value="{{ number_format($item->price_cents / 100, 2, '.', '') }}"></div><div class="field"><label>Descrição</label><textarea name="description" maxlength="2000" rows="3" style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:10px;font:inherit">{{ $item->description }}</textarea></div><button class="btn" type="submit">Salvar alterações</button></form></details><form method="post" action="{{ route('services.toggle', $item->id) }}" style="margin-top:8px">@csrf @method('PATCH')<button class="btn secondary" type="submit">{{ $item->status === 'active' ? 'Desativar' : 'Ativar' }}</button></form></td>@endif</tr>
    @endforeach
    </tbody></table></div>
    <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:16px">@if($items->previousPageUrl())<a class="btn secondary" href="{{ $items->previousPageUrl() }}">Anterior</a>@endif @if($items->nextPageUrl())<a class="btn secondary" href="{{ $items->nextPageUrl() }}">Próxima</a>@endif</div>
    @endif
</section>
@endsection
