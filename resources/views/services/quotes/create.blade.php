@extends('layouts.app', ['title' => 'Novo orçamento | RMC Flow Comércio Cloud'])
@section('content')
<section class="card">
    @php($editing = isset($quote))
    <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap"><div><p class="muted" style="margin-bottom:8px">MÓDULO DE SERVIÇOS</p><h1 class="title">{{ $editing ? 'Editar orçamento #'.str_pad((string)$quote->quote_number, 5, '0', STR_PAD_LEFT) : 'Novo orçamento' }}</h1><p class="muted">O orçamento não altera estoque, Caixa ou financeiro.</p></div><a class="btn secondary" href="{{ route('services.quotes.index') }}">Voltar aos orçamentos</a></div>
    @if ($errors->any())<div class="alert" role="alert">{{ $errors->first() }}</div>@endif
    @if ($customers->isEmpty())<div class="alert alert-warning">Cadastre um cliente antes de criar o orçamento.</div>@endif
    @if ($services->isEmpty() && $products->isEmpty())<div class="alert alert-warning">O catálogo ainda está vazio. Cadastre serviços ou produtos ou use itens manuais.</div>@endif
    <form method="post" action="{{ $editing ? route('services.quotes.update', $quote->id) : route('services.quotes.store') }}" id="quote-form" style="margin-top:20px">@csrf @if($editing)@method('PUT')@endif<input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">
        <div class="grid" style="max-width:960px">
            <div class="field"><label for="customer_id">Cliente</label><select id="customer_id" name="customer_id" required style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit"><option value="">Selecione</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string)old('customer_id', $customerId) === (string)$customer->id)>{{ $customer->name }}{{ $customer->phone ? ' — '.$customer->phone : '' }}</option>@endforeach</select>@if(auth()->user()->companyMemberships()->where('company_id', $companyId)->where('status', 'active')->where('role', 'Administrador')->exists())<a href="{{ route('customers.create') }}" target="_blank" rel="noopener">Cadastrar cliente em outra aba</a>@endif @error('customer_id')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="expires_at">Validade</label><input id="expires_at" name="expires_at" type="date" required value="{{ old('expires_at', $expiresAt) }}">@error('expires_at')<div class="error">{{ $message }}</div>@enderror</div>
            @if($appointment)<input type="hidden" name="service_appointment_id" value="{{ $appointment->id }}"><div class="field"><label>Agendamento vinculado</label><input value="#{{ $appointment->id }} · {{ $appointment->scheduled_at->format('d/m/Y H:i') }}" disabled></div>
            @else<div class="field"><label for="service_appointment_id">Agendamento relacionado (opcional)</label><select id="service_appointment_id" name="service_appointment_id" style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit"><option value="">Nenhum</option>@foreach($appointments as $appointmentOption)<option value="{{ $appointmentOption->id }}" @selected((string)old('service_appointment_id') === (string)$appointmentOption->id)>#{{ $appointmentOption->id }} · {{ $appointmentOption->customer?->name }} · {{ $appointmentOption->scheduled_at->format('d/m/Y H:i') }}</option>@endforeach</select></div>@endif
        </div>

        <h2>Itens do orçamento</h2><p class="muted">Serviços e produtos usam o preço atual do cadastro. Itens manuais permitem informar um valor específico.</p>
        <div id="quote-items" style="display:grid;gap:14px">
            @php($formItems = old('items', $seedItems))
            @foreach($formItems as $i => $item)
            <div class="company quote-item" style="display:block;margin:0">
                <div class="grid">
                    <div class="field"><label>Tipo</label><select name="items[{{ $i }}][item_type]" data-item-type style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit"><option value="service" @selected(($item['item_type'] ?? '') === 'service')>Serviço do catálogo</option><option value="product" @selected(($item['item_type'] ?? '') === 'product')>Produto / peça</option><option value="manual" @selected(($item['item_type'] ?? '') === 'manual')>Item manual</option></select></div>
                    <div class="field" data-source-wrap><label>Item do catálogo</label><select name="items[{{ $i }}][source_id]" data-source-id style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit"><option value="">Selecione</option><optgroup label="Serviços" data-kind="service">@foreach($services as $service)<option value="{{ $service->id }}" @selected(($item['item_type'] ?? '') === 'service' && (string)($item['source_id'] ?? '') === (string)$service->id)>{{ $service->name }} — R$ {{ number_format($service->price_cents / 100, 2, ',', '.') }}</option>@endforeach</optgroup><optgroup label="Produtos / peças" data-kind="product">@foreach($products as $product)<option value="{{ $product->id }}" @selected(($item['item_type'] ?? '') === 'product' && (string)($item['source_id'] ?? '') === (string)$product->id)>{{ $product->name }} — R$ {{ number_format($product->sale_price_cents / 100, 2, ',', '.') }}</option>@endforeach</optgroup></select></div>
                    <div class="field" data-manual-wrap><label>Nome do item manual</label><input name="items[{{ $i }}][name]" maxlength="180" value="{{ $item['name'] ?? '' }}"></div>
                    <div class="field"><label>Quantidade</label><input name="items[{{ $i }}][quantity]" type="number" min="0.001" max="100000" step="0.001" required value="{{ $item['quantity'] ?? '1.000' }}"></div>
                    <div class="field" data-manual-price-wrap><label>Preço unitário manual (R$)</label><input name="items[{{ $i }}][unit_price]" type="number" min="0" max="99999999.99" step="0.01" value="{{ $item['unit_price'] ?? '' }}"></div>
                    <div class="field" data-manual-wrap><label>Descrição do item (opcional)</label><input name="items[{{ $i }}][description]" maxlength="1000" value="{{ $item['description'] ?? '' }}"></div>
                </div>
                @if($i > 0)<button class="btn secondary" type="button" data-remove-item>Remover item</button>@endif
            </div>
            @endforeach
        </div>
        <template id="quote-item-template"><div class="company quote-item" style="display:block;margin:0"><div class="grid">
            <div class="field"><label>Tipo</label><select name="items[__INDEX__][item_type]" data-item-type style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit"><option value="service">Serviço do catálogo</option><option value="product">Produto / peça</option><option value="manual">Item manual</option></select></div>
            <div class="field" data-source-wrap><label>Item do catálogo</label><select name="items[__INDEX__][source_id]" data-source-id style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit"><option value="">Selecione</option><optgroup label="Serviços" data-kind="service">@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->name }} — R$ {{ number_format($service->price_cents / 100, 2, ',', '.') }}</option>@endforeach</optgroup><optgroup label="Produtos / peças" data-kind="product">@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }} — R$ {{ number_format($product->sale_price_cents / 100, 2, ',', '.') }}</option>@endforeach</optgroup></select></div>
            <div class="field" data-manual-wrap><label>Nome do item manual</label><input name="items[__INDEX__][name]" maxlength="180"></div>
            <div class="field"><label>Quantidade</label><input name="items[__INDEX__][quantity]" type="number" min="0.001" max="100000" step="0.001" required value="1.000"></div>
            <div class="field" data-manual-price-wrap><label>Preço unitário manual (R$)</label><input name="items[__INDEX__][unit_price]" type="number" min="0" max="99999999.99" step="0.01"></div>
            <div class="field" data-manual-wrap><label>Descrição do item (opcional)</label><input name="items[__INDEX__][description]" maxlength="1000"></div>
        </div><button class="btn secondary" type="button" data-remove-item>Remover item</button></div></template>
        <div class="actions" style="margin:16px 0"><button class="btn secondary" type="button" id="add-quote-item">Adicionar item</button></div>
        <div class="field" style="max-width:900px"><label for="notes">Observações do orçamento</label><textarea id="notes" name="notes" maxlength="4000" rows="3" style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:10px;font:inherit">{{ old('notes') }}</textarea></div>
        <button class="btn" type="submit" @disabled($customers->isEmpty())>{{ $editing ? 'Atualizar rascunho' : 'Salvar rascunho' }}</button>
    </form>
</section>
<script>
(() => {
    const list = document.getElementById('quote-items');
    const template = document.getElementById('quote-item-template');
    const indexes = Array.from(list.querySelectorAll('[name^="items["]')).map((field) => Number(field.name.match(/^items\[(\d+)\]/)?.[1] ?? -1));
    let nextIndex = Math.max(-1, ...indexes) + 1;
    const sync = (row) => {
        const type = row.querySelector('[data-item-type]').value;
        const manual = type === 'manual';
        row.querySelector('[data-source-wrap]').hidden = manual;
        row.querySelector('[data-source-id]').required = !manual;
        row.querySelectorAll('[data-manual-wrap]').forEach((field) => { field.hidden = !manual; });
        row.querySelector('[data-manual-wrap] input[name$="[name]"]').required = manual;
        row.querySelector('[data-manual-price-wrap]').hidden = !manual;
        row.querySelector('[data-manual-price-wrap] input').required = manual;
        row.querySelectorAll('[data-source-id] optgroup').forEach((group) => { group.hidden = group.dataset.kind !== type; });
        const source = row.querySelector('[data-source-id]');
        if (source.selectedOptions[0]?.parentElement?.dataset.kind !== type) source.value = '';
    };
    list.querySelectorAll('.quote-item').forEach((row) => {
        sync(row);
        row.querySelector('[data-item-type]').addEventListener('change', () => sync(row));
        row.querySelector('[data-remove-item]')?.addEventListener('click', () => row.remove());
    });
    document.getElementById('add-quote-item').addEventListener('click', () => {
        const index = nextIndex++;
        const fragment = template.content.cloneNode(true);
        fragment.querySelectorAll('[name]').forEach((field) => { field.name = field.name.replaceAll('__INDEX__', String(index)); });
        const row = fragment.querySelector('.quote-item');
        list.appendChild(fragment);
        const added = list.lastElementChild;
        sync(added);
        added.querySelector('[data-item-type]').addEventListener('change', () => sync(added));
        added.querySelector('[data-remove-item]').addEventListener('click', () => added.remove());
    });
})();
</script>
@endsection
