@extends('layouts.app', ['title' => 'Veículos e equipamentos | RMC Flow Comércio Cloud'])

@section('content')
<section class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap">
        <div>
            <p class="muted" style="margin-bottom:8px">MÓDULO DE SERVIÇOS</p>
            <h1 class="title">Veículos e equipamentos</h1>
            <p class="muted">Pesquise por placa, série, nome, fabricante, modelo, tipo ou cliente. Cadastros podem ser feitos na ficha do cliente ou durante a abertura da OS.</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('services.assets.create') }}">Cadastrar veículo / equipamento</a>
            <a class="btn secondary" href="{{ route('services.work-orders.index') }}">Ordens de serviço</a>
            <a class="btn secondary" href="{{ route('customers.index') }}">Clientes</a>
            <a class="btn secondary" href="{{ route('dashboard') }}">Painel</a>
        </div>
    </div>

    <form method="get" class="grid" style="max-width:950px;margin:18px 0">
        <div class="field">
            <label for="q">Busca</label>
            <input id="q" name="q" value="{{ $search }}" maxlength="100" placeholder="Placa, série, marca, modelo ou cliente">
        </div>
        <div class="field">
            <label for="status">Situação do cadastro</label>
            <select id="status" name="status" style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit">
                <option value="">Todos</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Ativos</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inativos</option>
            </select>
        </div>
        <div>
            <button class="btn secondary" type="submit">Pesquisar</button>
            <a class="btn secondary" href="{{ route('services.assets.index') }}">Limpar</a>
        </div>
    </form>

    @if ($assets->isEmpty())
        <p class="muted">Nenhum veículo/equipamento encontrado.</p>
    @else
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;text-align:left">
                <thead>
                    <tr>
                        <th style="padding:10px;border-bottom:1px solid #dbe3ed">Veículo/equipamento</th>
                        <th style="padding:10px;border-bottom:1px solid #dbe3ed">Identificação</th>
                        <th style="padding:10px;border-bottom:1px solid #dbe3ed">Cliente</th>
                        <th style="padding:10px;border-bottom:1px solid #dbe3ed">Situação</th>
                        <th style="padding:10px;border-bottom:1px solid #dbe3ed">Histórico de OS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($assets as $asset)
                        <tr>
                            <td style="padding:10px;border-bottom:1px solid #edf1f5">
                                <strong><a href="{{ route('services.assets.show', $asset->id) }}">{{ $asset->name }}</a></strong><br>
                                <span class="muted">
                                    {{ ['vehicle' => 'Veículo', 'motorcycle' => 'Motocicleta', 'equipment' => 'Equipamento', 'other' => 'Outro'][$asset->asset_type] ?? $asset->asset_type }}
                                    · {{ collect([$asset->manufacturer, $asset->model, $asset->year])->filter()->implode(' ') }}
                                </span>
                            </td>
                            <td style="padding:10px;border-bottom:1px solid #edf1f5">{{ $asset->identifier ?: '—' }}</td>
                            <td style="padding:10px;border-bottom:1px solid #edf1f5">
                                <a href="{{ route('customers.show', $asset->customer_id) }}#service-assets">{{ $asset->customer?->name ?? 'Cliente indisponível' }}</a>
                            </td>
                            <td style="padding:10px;border-bottom:1px solid #edf1f5">{{ $asset->status === 'active' ? 'Ativo' : 'Inativo' }}</td>
                            <td style="padding:10px;border-bottom:1px solid #edf1f5">
                                @if ($asset->workOrders->isEmpty())
                                    <span class="muted">Sem OS</span>
                                @else
                                    @foreach ($asset->workOrders as $order)
                                        <a href="{{ route('services.work-orders.show', $order->id) }}">#{{ str_pad((string) $order->work_order_number, 5, '0', STR_PAD_LEFT) }}</a>
                                        @if (! $loop->last)
                                            ·
                                        @endif
                                    @endforeach
                                    @if ($asset->work_orders_count > 5)
                                        <span class="muted"> e mais</span>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:16px">{{ $assets->links() }}</div>
    @endif
</section>
@endsection
