@extends('layouts.app', ['title' => 'OS #'.$order->work_order_number.' | RMC Flow Comércio Cloud'])

@section('content')
<section class="card">
    <div class="no-print" style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap">
        <div>
            <p class="muted">MÓDULO DE SERVIÇOS</p>
            <h1 class="title">Ordem de serviço #{{ str_pad((string) $order->work_order_number, 5, '0', STR_PAD_LEFT) }}</h1>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('services.work-orders.index') }}">Ordens de serviço</a>
            <button class="btn secondary" type="button" onclick="window.print()">Imprimir / Salvar PDF</button>
        </div>
    </div>

    <?php if (session('work_order_notice')) { ?>
        <div class="alert no-print alert-success" role="status">{{ session('work_order_notice') }}</div>
    <?php } ?>
    <?php if ($errors->any()) { ?>
        <div class="alert alert-danger no-print" role="alert">{{ $errors->first() }}</div>
    <?php } ?>
    <?php if ($order->status === 'cancelled') { ?>
        <div class="alert alert-warming">Cancelada em {{ $order->cancelled_at?->format('d/m/Y H:i') }}. Motivo: {{ $order->cancellation_reason }}. Solicitante: {{ $order->cancelledBy?->name ?? 'Sistema' }}. Autorizada por: {{ $order->cancellationApprover?->name ?? '—' }}.</div>
    <?php } ?>

    <div style="max-width:950px;margin:22px auto">
        <div style="display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap;border-bottom:2px solid #102c4d;padding-bottom:16px">
            <div>
                <h2 style="margin:0 0 8px">{{ $company->trade_name }}</h2>
                <?php if ($company->legal_name) { ?><div>{{ $company->legal_name }}</div><?php } ?>
                <?php if ($company->document) { ?><div>Documento: {{ $company->document }}</div><?php } ?>
                <?php if ($company->phone) { ?><div>Telefone: {{ $company->phone }}</div><?php } ?>
                <?php if ($addressText) { ?><div>{{ $addressText }}</div><?php } ?>
            </div>
            <div>
                <strong>Cliente:</strong> {{ $order->customer->name }}<br>
                <?php if ($order->customer->document) { ?><div>{{ $order->customer->document }}</div><?php } ?>
                <?php if ($order->customer->phone) { ?><div>{{ $order->customer->phone }}</div><?php } ?>
            </div>
            <div>
                <strong>Situação:</strong> {{ $statuses[$order->status] ?? $order->status }}<br>
                <strong>Aberta em:</strong> {{ $order->opened_at->format('d/m/Y H:i') }}
                <?php if ($order->quote) { ?><br><strong>Orçamento:</strong> #{{ str_pad((string) $order->quote->quote_number, 5, '0', STR_PAD_LEFT) }}<?php } ?>
                <?php if ($order->finalized_at) { ?><br><strong>Finalizada em:</strong> {{ $order->finalized_at->format('d/m/Y H:i') }}<?php } ?>
            </div>
        </div>

        <?php if ($order->asset_description || $order->asset_identifier || $order->asset_type) { ?>
            <div style="margin:18px 0">
                <strong>Veículo / equipamento</strong>
                <div>
                    {{ ['vehicle' => 'Veículo', 'motorcycle' => 'Motocicleta', 'equipment' => 'Equipamento', 'other' => 'Outro'][$order->asset_type] ?? $order->asset_type ?? 'Equipamento' }}
                    · {{ $order->asset_description ?: '—' }}
                    <?php if ($order->asset_manufacturer || $order->asset_model) { ?> · {{ collect([$order->asset_manufacturer, $order->asset_model])->filter()->implode(' ') }}<?php } ?>
                    <?php if ($order->asset_year) { ?> · {{ $order->asset_year }}<?php } ?>
                    <?php if ($order->asset_color) { ?> · {{ $order->asset_color }}<?php } ?>
                    <?php if ($order->asset_identifier) { ?> · Identificação: {{ $order->asset_identifier }}<?php } ?>
                </div>
                <?php if ($order->service_asset_id) { ?><a class="no-print" href="{{ route('services.assets.show', $order->service_asset_id) }}">Ver histórico deste veículo/equipamento</a><?php } ?>
            </div>
        <?php } ?>

        <?php if ($order->expected_at || $order->mileage || $order->fuel_level || $order->warranty_days) { ?>
            <div style="margin:18px 0">
                <strong>Dados de entrada</strong>
                <div>
                    <?php if ($order->expected_at) { ?>Previsão de entrega: {{ $order->expected_at->format('d/m/Y') }}<?php } ?>
                    <?php if ($order->mileage) { ?> · Quilometragem: {{ $order->mileage }}<?php } ?>
                    <?php if ($order->fuel_level) { ?> · Combustível: {{ $order->fuel_level }}<?php } ?>
                    <?php if ($order->warranty_days) { ?> · Garantia: {{ $order->warranty_days }} dias<?php } ?>
                </div>
            </div>
        <?php } ?>

        <?php if ($order->complaint) { ?>
            <div style="margin:18px 0"><strong>Relato do cliente</strong><p style="white-space:pre-line">{{ $order->complaint }}</p></div>
        <?php } ?>
        <?php if ($order->diagnosis) { ?>
            <div style="margin:18px 0"><strong>Diagnóstico</strong><p style="white-space:pre-line">{{ $order->diagnosis }}</p></div>
        <?php } ?>

        <?php if (! in_array($order->status, ['completed', 'awaiting_payment', 'ready', 'finalized', 'cancelled'], true) && ! $order->stock_applied_at) { ?>
            <div class="no-print" style="margin:18px 0;padding:16px;border:1px solid #cbd5e1;border-radius:8px">
                <h2>Peças e mão de obra do orçamento</h2>
                <p class="muted">Adicione os itens necessários após o diagnóstico. O estoque só será baixado quando o serviço for concluído.</p>
                <form method="post" action="{{ route('services.work-orders.items.add', $order->id) }}" class="grid" id="work-order-item-form" style="max-width:950px">
                    <?= csrf_field() ?>
                    <div class="field"><label for="item_type">Tipo de item</label><select id="item_type" name="type" required><option value="product">Peça / produto</option><option value="service">Serviço / mão de obra</option></select></div>
                    <div class="field" style="position:relative"><label for="item_search">Buscar peça ou serviço cadastrado</label><input id="item_search" type="search" autocomplete="off" placeholder="Digite nome, código de barras ou SKU"><div id="item_search_results" style="position:absolute;z-index:5;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-radius:8px;max-height:230px;overflow:auto;display:none"></div><select id="item_catalog_options" hidden><option value="">Selecione</option><?php foreach ($products as $product) { ?><option data-kind="product" data-search="{{ mb_strtolower($product->name.' '.$product->sku.' '.$product->barcode) }}" data-price="{{ number_format($product->sale_price_cents / 100, 2, '.', '') }}" value="{{ $product->id }}">{{ $product->name }} · SKU {{ $product->sku }} · EAN {{ $product->barcode }} · R$ {{ number_format($product->sale_price_cents / 100, 2, ',', '.') }}</option><?php } ?><?php foreach ($serviceCatalog as $catalogService) { ?><option data-kind="service" data-search="{{ mb_strtolower($catalogService->name.' '.$catalogService->description) }}" data-price="{{ number_format($catalogService->price_cents / 100, 2, '.', '') }}" value="{{ $catalogService->id }}">{{ $catalogService->name }} · R$ {{ number_format($catalogService->price_cents / 100, 2, ',', '.') }}</option><?php } ?></select><input type="hidden" id="item_product_id" name="product_id"><input type="hidden" id="item_service_id" name="service_catalog_id"></div>
                    <div class="field" id="item_name_wrap" hidden><label for="item_name">Serviço / mão de obra</label><input id="item_name" name="name" maxlength="180" placeholder="Selecione um serviço cadastrado na busca ao lado ou informe outro" autocomplete="off"></div>
                    <div class="field"><label for="item_quantity">Quantidade</label><input id="item_quantity" name="quantity" type="number" min="0.001" step="0.001" value="1" required></div>
                    <div class="field"><label for="item_price">Valor unitário (R$)</label><input id="item_price" name="unit_price" type="number" min="0" step="0.01" required></div>
                    <div class="field" style="grid-column:1/-1"><label for="item_description">Detalhes (opcional)</label><input id="item_description" name="description" maxlength="1000"></div>
                    <div class="field" style="grid-column:1/-1"><strong>Subtotal do item: <span id="item_subtotal">R$ 0,00</span></strong></div>
                    <div class="actions"><button class="btn" type="submit">Adicionar ao orçamento</button></div>
                </form>
                <script>
                (() => {
                    const type = document.getElementById('item_type');
                    const search = document.getElementById('item_search');
                    const results = document.getElementById('item_search_results');
                    const catalog = document.getElementById('item_catalog_options');
                    const productId = document.getElementById('item_product_id');
                    const serviceId = document.getElementById('item_service_id');
                    const name = document.getElementById('item_name');
                    const nameWrap = document.getElementById('item_name_wrap');
                    const price = document.getElementById('item_price');
                    const quantity = document.getElementById('item_quantity');
                    const subtotal = document.getElementById('item_subtotal');
                    const money = (n) => new Intl.NumberFormat('pt-BR', {style:'currency',currency:'BRL'}).format(n || 0);
                    const refreshTotal = () => { subtotal.textContent = money((parseFloat(quantity.value || 0) || 0) * (parseFloat(price.value || 0) || 0)); };
                    const renderResults = () => {
                        results.replaceChildren();
                        const q = search.value.trim().toLocaleLowerCase('pt-BR');
                        const options = [...catalog.options].filter(o => o.dataset.kind === type.value && (!q || (o.dataset.search || '').includes(q))).slice(0, 30);
                        options.forEach(option => {
                            const button = document.createElement('button');
                            button.type = 'button'; button.textContent = option.textContent; button.style.cssText = 'display:block;width:100%;text-align:left;padding:9px 12px;border:0;background:white;cursor:pointer';
                            button.addEventListener('click', () => {
                                productId.value = type.value === 'product' ? option.value : '';
                                serviceId.value = type.value === 'service' ? option.value : '';
                                search.value = option.textContent.trim();
                                price.value = option.dataset.price || '0.00';
                                if (type.value === 'service') name.value = option.textContent.split(' · R$')[0].trim();
                                results.style.display = 'none'; refreshTotal();
                            });
                            results.appendChild(button);
                        });
                        results.style.display = q && options.length ? 'block' : 'none';
                    };
                    type.addEventListener('change', () => {
                        productId.value = ''; serviceId.value = ''; search.value = ''; name.value = ''; price.value = '';
                        nameWrap.hidden = type.value !== 'service';
                        price.readOnly = type.value === 'product';
                        search.placeholder = type.value === 'product' ? 'Digite nome, código de barras ou SKU' : 'Busque um serviço cadastrado ou deixe em branco para informar outro';
                        refreshTotal(); renderResults();
                    });
                    search.addEventListener('input', renderResults);
                    search.addEventListener('focus', renderResults);
                    document.addEventListener('click', (event) => { if (!results.contains(event.target) && event.target !== search) results.style.display = 'none'; });
                    name.addEventListener('input', () => { serviceId.value = ''; if (type.value === 'service') { search.value = name.value; renderResults(); } });
                    price.addEventListener('input', refreshTotal); quantity.addEventListener('input', refreshTotal);
                    type.dispatchEvent(new Event('change'));
                })();
                </script>
            </div>
        <?php } ?>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;text-align:left">
                <thead><tr><th style="padding:10px;border-bottom:1px solid #9aa9bb">Item</th><th style="padding:10px;border-bottom:1px solid #9aa9bb">Qtd.</th><th style="padding:10px;border-bottom:1px solid #9aa9bb">Preço unitário</th><th style="padding:10px;border-bottom:1px solid #9aa9bb">Total</th></tr></thead>
                <tbody>
                    <?php foreach ($order->items as $item) { ?>
                        <tr>
                            <td style="padding:10px;border-bottom:1px solid #dbe3ed"><strong>{{ $item->name }}</strong><?php if ($item->description) { ?><br>{{ $item->description }}<?php } ?><?php if (! in_array($order->status, ['completed', 'awaiting_payment', 'ready', 'finalized', 'cancelled'], true) && ! $order->stock_applied_at) { ?><form class="no-print" method="post" action="{{ route('services.work-orders.items.remove', [$order->id, $item->id]) }}" style="margin-top:6px"><?= csrf_field() ?><?= method_field('DELETE') ?><button class="btn secondary" type="submit">Remover</button></form><?php } ?></td>
                            <td style="padding:10px;border-bottom:1px solid #dbe3ed">{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                            <td style="padding:10px;border-bottom:1px solid #dbe3ed">R$ {{ number_format($item->unit_price_cents / 100, 2, ',', '.') }}</td>
                            <td style="padding:10px;border-bottom:1px solid #dbe3ed">R$ {{ number_format($item->total_cents / 100, 2, ',', '.') }}</td>
                        </tr>
                    <?php } ?>
                </tbody>
                <tfoot><tr><th colspan="3" style="padding:12px;text-align:right">TOTAL</th><th style="padding:12px">R$ {{ number_format($order->totalCents() / 100, 2, ',', '.') }}</th></tr></tfoot>
            </table>
        </div>

        <?php if ($order->receivable) { ?>
            <div style="margin:22px 0;padding:16px;border:1px solid #cbd5e1;border-radius:8px">
                <h2 style="margin-top:0">Financeiro da OS</h2>
                <p>Valor: R$ {{ number_format($order->receivable->amount_cents / 100, 2, ',', '.') }} · Recebido: R$ {{ number_format($order->receivable->paid_cents / 100, 2, ',', '.') }} · Saldo: R$ {{ number_format($order->receivable->remainingCents() / 100, 2, ',', '.') }}</p>
                <p>Situação: {{ ['pending' => 'Pendente', 'partial' => 'Parcial', 'paid' => 'Paga', 'cancelled' => 'Cancelada'][$order->receivable->status] ?? $order->receivable->status }}</p>
                <?php if (in_array($order->receivable->status, ['pending', 'partial'], true)) { ?>
                    <h3>Registrar recebimento</h3>
                    <form method="post" action="{{ route('receivables.pay', $order->receivable->id) }}" class="grid" style="max-width:850px">
                        <?= csrf_field() ?>
                        <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <input type="hidden" name="return_q" value="OS {{ $order->work_order_number }}">
                        <div class="field"><label for="amount">Valor recebido</label><input id="amount" type="number" name="amount" min="0.01" max="{{ number_format($order->receivable->remainingCents() / 100, 2, '.', '') }}" step="0.01" value="{{ number_format($order->receivable->remainingCents() / 100, 2, '.', '') }}" required></div>
                        <div class="field"><label for="payment_method">Meio de pagamento</label><select id="payment_method" name="payment_method" required style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit">
                            <option value="pix">PIX</option><option value="cash" <?= $cashSessionOpen ? '' : 'disabled' ?>>Dinheiro <?= $cashSessionOpen ? '' : '(abra o caixa primeiro)' ?></option><option value="debit_card">Débito</option><option value="credit_card">Crédito</option><option value="bank_transfer">Transferência</option><option value="other">Outro</option>
                        </select></div>
                        <div class="field"><label for="paid_at">Data e hora</label><input id="paid_at" type="datetime-local" name="paid_at" value="{{ now()->format('Y-m-d\\TH:i') }}" max="{{ now()->format('Y-m-d\\TH:i') }}" required></div>
                        <div class="field"><label for="reference">Referência (opcional)</label><input id="reference" name="reference" maxlength="120"></div>
                        <div class="actions" style="grid-column:1/-1"><button class="btn" type="submit">Registrar recebimento</button></div>
                    </form>
                <?php } ?>
                <?php if ($order->receivable->payments->isNotEmpty()) { ?>
                    <h3>Histórico de recebimentos</h3><ul>
                    <?php foreach ($order->receivable->payments as $payment) { ?>
                        <li>{{ $payment->paid_at->format('d/m/Y H:i') }} · {{ ['cash' => 'Dinheiro', 'pix' => 'PIX', 'debit_card' => 'Débito', 'credit_card' => 'Crédito', 'bank_transfer' => 'Transferência', 'other' => 'Outro'][$payment->payment_method] ?? $payment->payment_method }} · R$ {{ number_format($payment->amount_cents / 100, 2, ',', '.') }}<?php if ($payment->reference) { ?> · Ref. {{ $payment->reference }}<?php } ?> · {{ $payment->creator?->name ?? 'Usuário indisponível' }}</li>
                    <?php } ?>
                    </ul>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if ($order->status === 'cancelled') { ?>
            <div class="no-print" style="margin:22px 0;padding:16px;border:1px solid #cbd5e1;border-radius:8px">
                <h2>Devoluções financeiras</h2>
                <?php if ($order->refunds->isEmpty()) { ?><p class="muted">Nenhuma devolução financeira registrada.</p><?php } ?>
                <?php foreach ($order->refunds as $refund) { ?>
                    <p>{{ $refund->refunded_at->format('d/m/Y H:i') }} · {{ ['cash' => 'Dinheiro', 'pix' => 'PIX', 'debit_card' => 'Débito', 'credit_card' => 'Crédito', 'bank_transfer' => 'Transferência', 'other' => 'Outro'][$refund->payment_method] ?? $refund->payment_method }} · R$ {{ number_format($refund->amount_cents / 100, 2, ',', '.') }} · {{ $refund->reason }} · solicitada por {{ $refund->creator?->name ?? 'Sistema' }} · autorizada por {{ $refund->approver?->name ?? '—' }}<?php if ($refund->reference) { ?> · Ref.: {{ $refund->reference }}<?php } ?></p>
                <?php } ?>
                <?php if ($canRequestCancellation && $refundOptions->isNotEmpty()) { ?>
                    <h3>Registrar devolução</h3>
                    <p class="muted">A devolução usa uma forma de pagamento já recebida e respeita o saldo que ainda pode ser devolvido. Dinheiro é retirado do caixa aberto no momento da devolução.</p>
                    <form method="post" action="{{ route('services.work-orders.refund', $order->id) }}" class="grid" style="max-width:900px">
                        <?= csrf_field() ?>
                        <input type="hidden" name="request_key" value="{{ $refundRequestKey }}">
                        <div class="field"><label for="refund_amount">Valor (R$)</label><input id="refund_amount" name="amount" type="number" min="0.01" step="0.01" required value="{{ old('amount') }}"></div>
                        <div class="field"><label for="refund_method">Forma de devolução</label><select id="refund_method" name="payment_method" required style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit">
                            <?php foreach ($refundOptions as $method => $option) { ?>
                                <option value="{{ $method }}" data-remaining="{{ number_format($option['remaining_cents'] / 100, 2, '.', '') }}" <?= $method === 'cash' && !$cashSessionOpen ? 'disabled' : '' ?> <?= old('payment_method', $defaultRefundMethod) === $method ? 'selected' : '' ?>>{{ $option['label'] }} · disponível R$ {{ number_format($option['remaining_cents'] / 100, 2, ',', '.') }}<?= $method === 'cash' && !$cashSessionOpen ? ' (abra o caixa)' : '' ?></option>
                            <?php } ?>
                        </select></div>
                        <div class="field"><label for="refunded_at">Data e hora</label><input id="refunded_at" name="refunded_at" type="datetime-local" max="{{ now()->format('Y-m-d\\TH:i') }}" value="{{ now()->format('Y-m-d\\TH:i') }}" required></div>
                        <div class="field"><label for="refund_reference">Referência (opcional)</label><input id="refund_reference" name="reference" maxlength="120" value="{{ old('reference') }}"></div>
                        <div class="field" style="grid-column:1/-1"><label for="refund_reason">Motivo</label><input id="refund_reason" name="reason" minlength="5" maxlength="180" required value="{{ old('reason') }}"></div>
                        <?php if ($role === 'Administrador') { ?>
                            <div class="field" style="grid-column:1/-1"><label for="refund_admin_password">Confirme sua senha de administrador</label><input id="refund_admin_password" name="current_password" type="password" autocomplete="current-password" required></div>
                        <?php } else { ?>
                            <div class="field"><label for="refund_approval_email">E-mail do administrador</label><input id="refund_approval_email" name="approval_email" type="email" autocomplete="username" required></div>
                            <div class="field"><label for="refund_approval_password">Senha do administrador</label><input id="refund_approval_password" name="approval_password" type="password" autocomplete="new-password" required></div>
                        <?php } ?>
                        <div class="actions"><button class="btn" type="submit" onclick="return confirm('Registrar esta devolução financeira da OS?')">Registrar devolução</button></div>
                    </form>
                <?php } elseif (!$canRequestCancellation) { ?>
                    <p class="muted">A devolução exige autorização de um administrador.</p>
                <?php } elseif ($refundOptions->isEmpty()) { ?>
                    <p class="muted">Não há saldo recebido disponível para devolução.</p>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if ($order->notes) { ?><div style="margin-top:18px"><strong>Observações</strong><p style="white-space:pre-line">{{ $order->notes }}</p></div><?php } ?>
        <?php if ($order->warranty_days > 0 && $order->finalized_at) { ?><p><strong>Garantia:</strong> até {{ $order->finalized_at->copy()->addDays($order->warranty_days)->format('d/m/Y') }} ({{ $order->warranty_days }} dias)</p><?php } ?>
        <?php if ($order->stock_applied_at) { ?><p class="muted">Peças baixadas do estoque em {{ $order->stock_applied_at->format('d/m/Y H:i') }}.<?php if ($order->stock_reversed_at) { ?> Estorno em {{ $order->stock_reversed_at->format('d/m/Y H:i') }}<?php } ?></p><?php } ?>
    </div>

    <?php if ($order->status === 'approved') { ?>
        <form class="no-print" method="post" action="{{ route('services.work-orders.status', $order->id) }}" style="margin:18px 0">
            <?= csrf_field() ?><?= method_field('PATCH') ?>
            <input type="hidden" name="status" value="in_maintenance">
            <button class="btn" type="submit">Avançar status para Em manutenção</button>
        </form>
    <?php } ?>
    <?php if ($order->status !== 'cancelled' && $order->status !== 'finalized' && ($nextStatuses || $canRequestCancellation)) { ?>
        <div class="no-print" style="border-top:1px solid #e1e8f0;padding-top:18px">
            <h2>Atualizar andamento</h2>
            <form method="post" action="{{ route('services.work-orders.status', $order->id) }}" id="work-order-status-form" class="grid" style="max-width:950px">
                <?= csrf_field() ?><?= method_field('PATCH') ?>
                <div class="field"><label for="status">Situação da OS</label><select id="status" name="status" required style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit">
                    <?php foreach ($nextStatuses as $key) { ?><option value="{{ $key }}" <?= old('status', $nextStatuses[0] ?? '') === $key ? 'selected' : '' ?>>{{ $statuses[$key] }}</option><?php } ?>
                    <?php if ($canRequestCancellation) { ?><option value="cancelled" <?= old('status') === 'cancelled' ? 'selected' : '' ?>>Cancelar OS</option><?php } ?>
                </select></div>
                <div class="field"><label for="warranty_days">Garantia em dias</label><input id="warranty_days" name="warranty_days" type="number" min="0" max="3650" value="{{ old('warranty_days', $order->warranty_days) }}"></div>
                <div class="field" style="grid-column:1/-1"><label for="diagnosis">Diagnóstico</label><textarea id="diagnosis" name="diagnosis" maxlength="4000" rows="3" style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:10px;font:inherit">{{ old('diagnosis', $order->diagnosis) }}</textarea></div>
                <div class="field" style="grid-column:1/-1"><label for="notes">Observações</label><textarea id="notes" name="notes" maxlength="4000" rows="2" style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:10px;font:inherit">{{ old('notes', $order->notes) }}</textarea></div>
                <?php if ($canRequestCancellation) { ?>
                    <div class="field" id="cancel-reason-wrap" style="grid-column:1/-1" hidden><label for="cancel_reason">Motivo do cancelamento</label><input id="cancel_reason" name="reason" minlength="5" maxlength="180" value="{{ old('reason') }}" data-cancel-required></div>
                    <?php if ($role === 'Administrador') { ?>
                        <div class="field" id="cancel-admin-wrap" style="grid-column:1/-1" hidden><label for="cancel_admin_password">Confirme sua senha de administrador</label><input id="cancel_admin_password" name="current_password" type="password" autocomplete="current-password" data-cancel-required></div>
                    <?php } else { ?>
                        <div class="field" id="cancel-staff-email-wrap" hidden><label for="cancel_approval_email">E-mail do administrador</label><input id="cancel_approval_email" name="approval_email" type="email" autocomplete="username" data-cancel-required></div>
                        <div class="field" id="cancel-staff-password-wrap" hidden><label for="cancel_approval_password">Senha do administrador</label><input id="cancel_approval_password" name="approval_password" type="password" autocomplete="new-password" data-cancel-required></div>
                    <?php } ?>
                <?php } ?>
                <div class="actions"><button class="btn" type="submit" onclick="return document.getElementById('status').value !== 'cancelled' || confirm('Confirmar cancelamento da OS? O estoque será estornado e os valores já recebidos continuarão registrados?')">Salvar andamento</button></div>
            </form>
        </div>
    <?php } ?>

    <?php if ($canRequestCancellation) { ?>
        <script>
        (() => {
            const status = document.getElementById('status');
            const form = document.getElementById('work-order-status-form');
            if (!status || !form) return;
            const syncCancelFields = () => {
                const active = status.value === 'cancelled';
                ['cancel-reason-wrap', 'cancel-admin-wrap', 'cancel-staff-email-wrap', 'cancel-staff-password-wrap'].forEach((id) => {
                    const wrap = document.getElementById(id);
                    if (wrap) wrap.hidden = !active;
                });
                form.querySelectorAll('[data-cancel-required]').forEach((field) => { field.required = active; });
            };
            status.addEventListener('change', syncCancelFields);
            syncCancelFields();
        })();
        </script>
    <?php } ?>
</section>
<style>@media print { body { background:white } .top,.footer,.no-print { display:none!important } .container { width:100%;margin:0 } .card { border:0;box-shadow:none;padding:0 } }</style>
@endsection
