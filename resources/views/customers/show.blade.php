@extends('layouts.app', ['title' => 'Histórico de '.$record->name.' | RMC Flow Comércio Cloud'])

@section('content')
<section class="card">
    <div class="actions" style="justify-content:space-between;align-items:flex-start;flex-wrap:wrap">
        <div>
            <p class="muted">CLIENTE</p>
            <h1 class="title">{{ $record->name }}</h1>
            <p class="muted">
                {{ $record->document ?: 'Sem documento' }} ·
                {{ $record->phone ?: 'Sem telefone' }} ·
                {{ $record->email ?: 'Sem e-mail' }}
            </p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('customers.index') }}">Voltar aos clientes</a>
            <a class="btn secondary" href="{{ route('customers.edit', $record->id) }}">Editar cadastro</a>
            <a class="btn secondary" href="{{ route('receivables.manual.create', ['customer_id' => $record->id]) }}">Lançar receita</a>
            <a class="btn" href="{{ route('sales.create', ['customer_id' => $record->id]) }}">Nova venda</a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin:18px 0">
        <div class="company" style="display:block;margin:0">
            <span class="muted">Vendas registradas</span>
            <strong style="display:block;font-size:23px">{{ number_format($sales->total(), 0, ',', '.') }}</strong>
        </div>
        <div class="company" style="display:block;margin:0">
            <span class="muted">Contas a receber abertas</span>
            <strong style="display:block;font-size:23px">R$ {{ number_format($openReceivableCents / 100, 2, ',', '.') }}</strong>
        </div>
    </div>

    <h2>Histórico de vendas</h2>
    @if ($sales->isEmpty())
        <p class="muted">Este cliente ainda não possui vendas registradas.</p>
    @else
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;text-align:left">
                <thead>
                    <tr>
                        <th style="padding:9px">Venda</th>
                        <th style="padding:9px">Data</th>
                        <th style="padding:9px">Total</th>
                        <th style="padding:9px">Recebido</th>
                        <th style="padding:9px">Em aberto</th>
                        <th style="padding:9px">Situação</th>
                        <th style="padding:9px">Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sales as $sale)
                        <tr>
                            <td style="padding:9px">#{{ $sale->sale_number }}</td>
                            <td style="padding:9px">{{ $sale->sold_at->format('d/m/Y H:i') }}</td>
                            <td style="padding:9px">R$ {{ number_format($sale->total_cents / 100, 2, ',', '.') }}</td>
                            <td style="padding:9px">R$ {{ number_format($sale->paid_cents / 100, 2, ',', '.') }}</td>
                            <td style="padding:9px">R$ {{ number_format($sale->balance_cents / 100, 2, ',', '.') }}</td>
                            <td style="padding:9px">{{ $sale->status === 'cancelled' ? 'Cancelada' : 'Concluída' }}</td>
                            <td style="padding:9px"><a class="btn secondary" href="{{ route('sales.show', $sale) }}">Abrir</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:14px">{{ $sales->links() }}</div>
    @endif

    <h2 style="margin-top:26px">Parcelas em aberto</h2>
    @if ($receivables->isEmpty())
        <p class="muted">Não há parcelas pendentes para este cliente.</p>
    @else
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;text-align:left">
                <thead>
                    <tr>
                        <th style="padding:9px">Origem</th>
                        <th style="padding:9px">Vencimento</th>
                        <th style="padding:9px">Valor</th>
                        <th style="padding:9px">Recebido</th>
                        <th style="padding:9px">Saldo</th>
                        <th style="padding:9px">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($receivables as $row)
                        <tr>
                            <td style="padding:9px">
                                @if ($row->sale_id)
                                    Venda #{{ $row->sale?->sale_number }} / parcela {{ $row->installment_number }}
                                @else
                                    {{ $row->description ?: 'Lançamento manual' }} / parcela {{ $row->installment_number }}
                                @endif
                            </td>
                            <td style="padding:9px">{{ $row->due_date->format('d/m/Y') }}</td>
                            <td style="padding:9px">R$ {{ number_format($row->amount_cents / 100, 2, ',', '.') }}</td>
                            <td style="padding:9px">R$ {{ number_format($row->paid_cents / 100, 2, ',', '.') }}</td>
                            <td style="padding:9px">R$ {{ number_format($row->remainingCents() / 100, 2, ',', '.') }}</td>
                            <td style="padding:9px">
                                <a class="btn secondary" href="{{ route('receivables.index', ['status' => 'open', 'customer_id' => $record->id]) }}">Contas a receber</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($servicesEnabled)
        <div id="service-assets" style="border-top:1px solid #e1e8f0;margin-top:28px;padding-top:20px">
            <h2>Veículos e equipamentos</h2>
            <p class="muted">Cadastre os itens atendidos para reutilizá-los nas ordens de serviço e consultar o histórico por veículo/equipamento.</p>

            @if (session('asset_notice'))
                <div class="alert alert-success" role="status">{{ session('asset_notice') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
            @endif

            <details style="margin:14px 0">
                <summary class="btn secondary">Cadastrar veículo/equipamento</summary>
                <form method="post" action="{{ route('services.assets.store', $record->id) }}" class="grid" style="max-width:950px;margin-top:14px">
                    @csrf
                    <div class="field">
                        <label for="asset_type">Tipo</label>
                        <select id="asset_type" name="asset_type" required style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit">
                            <option value="vehicle">Veículo</option>
                            <option value="motorcycle">Motocicleta</option>
                            <option value="equipment">Equipamento</option>
                            <option value="other">Outro</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="asset_name">Nome/descrição</label>
                        <input id="asset_name" name="name" maxlength="180" required placeholder="Ex.: Honda Civic, notebook Dell">
                    </div>
                    <div class="field">
                        <label for="asset_identifier">Placa, série ou identificação</label>
                        <input id="asset_identifier" name="identifier" maxlength="80">
                    </div>
                    <div class="field">
                        <label for="asset_manufacturer">Fabricante</label>
                        <input id="asset_manufacturer" name="manufacturer" maxlength="120">
                    </div>
                    <div class="field">
                        <label for="asset_model">Modelo</label>
                        <input id="asset_model" name="model" maxlength="120">
                    </div>
                    <div class="field">
                        <label for="asset_year">Ano</label>
                        <input id="asset_year" name="year" type="number" min="1900" max="{{ now()->year + 2 }}">
                    </div>
                    <div class="field">
                        <label for="asset_color">Cor</label>
                        <input id="asset_color" name="color" maxlength="60">
                    </div>
                    <div class="field" style="grid-column:1/-1">
                        <label for="asset_notes">Observações</label>
                        <textarea id="asset_notes" name="notes" maxlength="2000" rows="2" style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:10px;font:inherit"></textarea>
                    </div>
                    <div class="actions">
                        <button class="btn" type="submit">Salvar no cadastro do cliente</button>
                    </div>
                </form>
            </details>

            @if ($assets->isEmpty())
                <p class="muted">Nenhum veículo/equipamento cadastrado.</p>
            @else
                <div style="display:grid;gap:14px">
                    @foreach ($assets as $asset)
                        <article class="company" style="display:block;margin:0">
                            <div style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap">
                                <div>
                                    <strong><a href="{{ route('services.assets.show', $asset->id) }}">{{ $asset->name }}</a></strong>
                                    <span class="muted">({{ ['vehicle' => 'Veículo', 'motorcycle' => 'Motocicleta', 'equipment' => 'Equipamento', 'other' => 'Outro'][$asset->asset_type] ?? $asset->asset_type }})</span>
                                    <div>{{ collect([$asset->manufacturer, $asset->model, $asset->year, $asset->color])->filter()->implode(' · ') }}</div>
                                    @if ($asset->identifier)
                                        <div>Identificação: {{ $asset->identifier }}</div>
                                    @endif
                                    @if ($asset->notes)
                                        <div class="muted">{{ $asset->notes }}</div>
                                    @endif
                                </div>
                                <div>
                                    <span class="muted">{{ $asset->status === 'active' ? 'Ativo' : 'Inativo' }}</span>
                                    <form method="post" action="{{ route('services.assets.status', [$record->id, $asset->id]) }}" style="display:inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $asset->status === 'active' ? 'inactive' : 'active' }}">
                                        <button class="btn secondary" type="submit">{{ $asset->status === 'active' ? 'Desativar' : 'Reativar' }}</button>
                                    </form>
                                </div>
                            </div>

                            <h3 style="margin:14px 0 6px">Histórico de serviços</h3>
                            @if ($asset->workOrders->isEmpty())
                                <p class="muted">Ainda sem ordens de serviço vinculadas.</p>
                            @else
                                <ul style="margin:0">
                                    @foreach ($asset->workOrders as $workOrder)
                                        <li>
                                            <a href="{{ route('services.work-orders.show', $workOrder->id) }}">OS #{{ str_pad((string) $workOrder->work_order_number, 5, '0', STR_PAD_LEFT) }}</a>
                                            · {{ $workOrder->opened_at->format('d/m/Y') }}
                                            · {{ ['open' => 'Aberta', 'diagnosis' => 'Em diagnóstico', 'in_progress' => 'Em execução', 'completed' => 'Serviço concluído', 'awaiting_payment' => 'Aguardando pagamento', 'ready' => 'Pronta para retirada', 'finalized' => 'Finalizada', 'cancelled' => 'Cancelada'][$workOrder->status] ?? $workOrder->status }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</section>
@endsection
