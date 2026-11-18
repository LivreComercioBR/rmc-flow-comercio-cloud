@extends('layouts.app', ['title' => 'Novo orçamento | RMC Flow Comércio Cloud'])
@section('content')
<section class="card">
    @php($editing = isset($quote))
    <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap"><div><p class="muted" style="margin-bottom:8px">MÓDULO DE SERVIÇOS</p><h1 class="title">{{ $editing ? 'Editar orçamento #'.str_pad((string)$quote->quote_number, 5, '0', STR_PAD_LEFT) : 'Novo orçamento' }}</h1><p class="muted">O orçamento não altera estoque, Caixa ou financeiro.</p></div><a class="btn secondary" href="{{ route('services.quotes.index') }}">Voltar aos orçamentos</a></div>
    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @if ($customers->isEmpty())<div class="alert alert-warming">Cadastre um cliente antes de criar o orçamento.</div>@endif
    @if ($services->isEmpty() && $products->isEmpty())<div class="alert alert-warming">O catálogo ainda está vazio. Cadastre serviços ou produtos ou use itens manuais.</div>@endif
    <form method="post" action="{{ $editing ? route('services.quotes.update', $quote->id) : route('services.quotes.store') }}" id="quote-form" style="margin-top:20px">@csrf @if($editing)@method('PUT')@endif<input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">
        <div class="grid" style="max-width:960px">
            <div class="field"><label for="customer-search">Buscar cliente</label><input id="customer-search" type="search" autocomplete="off" placeholder="Digite o nome, telefone ou documento" style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit"><div id="customer-results" style="position:relative;z-index:5;background:white;border:1px solid #cbd5e1;border-radius:8px;max-height:220px;overflow:auto;display:none"></div><select id="customer_id" name="customer_id" required hidden><option value="">Selecione</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" data-search="{{ mb_strtolower($customer->name.' '.$customer->phone.' '.$customer->document) }}" @selected((string)old('customer_id', $customerId) === (string)$customer->id)>{{ $customer->name }}{{ $customer->phone ? ' — '.$customer->phone : '' }}</option>@endforeach</select>@if(auth()->user()->companyMemberships()->where('company_id', $companyId)->where('status', 'active')->where('role', 'Administrador')->exists())<a href="{{ route('customers.create') }}" target="_blank" rel="noopener">Cadastrar cliente em outra aba</a>@endif @error('customer_id')<div class="error">{{ $message }}</div>@enderror</div>
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
                    <div class="field" data-source-wrap><label>Buscar serviço ou produto</label><input type="search" data-source-search autocomplete="off" placeholder="Digite para buscar no catálogo" style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit"><div data-source-results style="position:relative;z-index:4;background:white;border:1px solid #cbd5e1;border-radius:8px;max-height:220px;overflow:auto;display:none"></div><select name="items[{{ $i }}][source_id]" data-source-id hidden><option value="">Selecione</option><optgroup label="Serviços" data-kind="service">@foreach($services as $service)<option value="{{ $service->id }}" data-price="{{ number_format($service->price_cents / 100, 2, '.', '') }}" data-search="{{ mb_strtolower($service->name.' '.$service->description) }}" @selected(($item['item_type'] ?? '') === 'service' && (string)($item['source_id'] ?? '') === (string)$service->id)>{{ $service->name }} — R$ {{ number_format($service->price_cents / 100, 2, ',', '.') }}</option>@endforeach</optgroup><optgroup label="Produtos / peças" data-kind="product">@foreach($products as $product)<option value="{{ $product->id }}" data-price="{{ number_format($product->sale_price_cents / 100, 2, '.', '') }}" data-search="{{ mb_strtolower($product->name.' '.$product->sku.' '.$product->barcode) }}" @selected(($item['item_type'] ?? '') === 'product' && (string)($item['source_id'] ?? '') === (string)$product->id)>{{ $product->name }} — R$ {{ number_format($product->sale_price_cents / 100, 2, ',', '.') }}</option>@endforeach</optgroup></select></div>
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
            <div class="field" data-source-wrap><label>Buscar serviço ou produto</label><input type="search" data-source-search autocomplete="off" placeholder="Digite para buscar no catálogo" style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit"><div data-source-results style="position:relative;z-index:4;background:white;border:1px solid #cbd5e1;border-radius:8px;max-height:220px;overflow:auto;display:none"></div><select name="items[__INDEX__][source_id]" data-source-id hidden><option value="">Selecione</option><optgroup label="Serviços" data-kind="service">@foreach($services as $service)<option value="{{ $service->id }}" data-price="{{ number_format($service->price_cents / 100, 2, '.', '') }}" data-search="{{ mb_strtolower($service->name.' '.$service->description) }}">{{ $service->name }} — R$ {{ number_format($service->price_cents / 100, 2, ',', '.') }}</option>@endforeach</optgroup><optgroup label="Produtos / peças" data-kind="product">@foreach($products as $product)<option value="{{ $product->id }}" data-price="{{ number_format($product->sale_price_cents / 100, 2, '.', '') }}" data-search="{{ mb_strtolower($product->name.' '.$product->sku.' '.$product->barcode) }}">{{ $product->name }} — R$ {{ number_format($product->sale_price_cents / 100, 2, ',', '.') }}</option>@endforeach</optgroup></select></div>
            <div class="field" data-manual-wrap><label>Nome do item manual</label><input name="items[__INDEX__][name]" maxlength="180"></div>
            <div class="field"><label>Quantidade</label><input name="items[__INDEX__][quantity]" type="number" min="0.001" max="100000" step="0.001" required value="1.000"></div>
            <div class="field" data-manual-price-wrap><label>Preço unitário manual (R$)</label><input name="items[__INDEX__][unit_price]" type="number" min="0" max="99999999.99" step="0.01"></div>
            <div class="field" data-manual-wrap><label>Descrição do item (opcional)</label><input name="items[__INDEX__][description]" maxlength="1000"></div>
        </div><button class="btn secondary" type="button" data-remove-item>Remover item</button></div></template>
        <div class="actions" style="margin:16px 0"><button class="btn secondary" type="button" id="add-quote-item">Adicionar item</button></div>
        <div class="company" style="margin:0 0 18px;padding:16px"><strong>Valor total do orçamento</strong><div id="quote-total" style="font-size:1.5rem;font-weight:700;margin-top:6px">R$ 0,00</div><small class="muted">O total considera os itens, preços e quantidades informados acima.</small></div>
        <div class="field" style="max-width:900px"><label for="notes">Observações do orçamento</label><textarea id="notes" name="notes" maxlength="4000" rows="3" style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:10px;font:inherit">{{ old('notes') }}</textarea></div>
        <button class="btn" type="submit" @disabled($customers->isEmpty())>{{ $editing ? 'Atualizar rascunho' : 'Salvar rascunho' }}</button>
    </form>
</section>
<script>
(() => {
    const list = document.getElementById('quote-items');
    const template = document.getElementById('quote-item-template');
    const totalLabel = document.getElementById('quote-total');
    const money = (value) => new Intl.NumberFormat('pt-BR', {style:'currency', currency:'BRL'}).format(value || 0);
    const recalculate = () => {
        let total = 0;
        list.querySelectorAll('.quote-item').forEach((row) => {
            const type = row.querySelector('[data-item-type]').value;
            const quantity = parseFloat(row.querySelector('input[name$="[quantity]"]').value || 0) || 0;
            let unit = 0;
            if (type === 'manual') unit = parseFloat(row.querySelector('input[name$="[unit_price]"]').value || 0) || 0;
            else {
                const select = row.querySelector('[data-source-id]');
                const selected = [...select.options].find((option) => option.selected && option.parentElement?.dataset.kind === type);
                unit = parseFloat(selected?.dataset.price || 0) || 0;
            }
            total += quantity * unit;
            const rowTotal = row.querySelector('[data-row-total]');
            if (rowTotal) rowTotal.textContent = money(quantity * unit);
        });
        totalLabel.textContent = money(total);
    };
    const sync = (row) => {
        const type = row.querySelector('[data-item-type]').value;
        const manual = type === 'manual';
        const sourceWrap = row.querySelector('[data-source-wrap]');
        const source = row.querySelector('[data-source-id]');
        const search = row.querySelector('[data-source-search]');
        const results = row.querySelector('[data-source-results]');
        sourceWrap.hidden = manual;
        source.required = !manual;
        row.querySelectorAll('[data-manual-wrap]').forEach((field) => { field.hidden = !manual; });
        row.querySelector('[data-manual-wrap] input[name$="[name]"]').required = manual;
        row.querySelector('[data-manual-price-wrap]').hidden = !manual;
        row.querySelector('[data-manual-price-wrap] input').required = manual;
        if (source.selectedOptions[0]?.parentElement?.dataset.kind !== type) {
            source.value = '';
            if (search) search.value = '';
        }
        if (search) search.placeholder = type === 'product' ? 'Buscar por nome, SKU ou código de barras' : 'Buscar serviço pelo nome';
        if (results) results.style.display = 'none';
        recalculate();
    };
    const attachRow = (row) => {
        const type = row.querySelector('[data-item-type]');
        const source = row.querySelector('[data-source-id]');
        const search = row.querySelector('[data-source-search]');
        const results = row.querySelector('[data-source-results]');
        if (source.selectedOptions[0]?.value && search) search.value = source.selectedOptions[0].textContent.trim();
        const renderResults = () => {
            results.replaceChildren();
            const query = search.value.trim().toLocaleLowerCase('pt-BR');
            if (!query) { results.style.display = 'none'; return; }
            const options = [...source.options].filter((option) => option.value && option.parentElement?.dataset.kind === type.value && (option.dataset.search || option.textContent.toLocaleLowerCase('pt-BR')).includes(query)).slice(0, 30);
            options.forEach((option) => {
                const button = document.createElement('button');
                button.type = 'button'; button.textContent = option.textContent.trim();
                button.style.cssText = 'display:block;width:100%;text-align:left;padding:9px 12px;border:0;background:white;cursor:pointer';
                const chooseOption = (event) => {
                    event.preventDefault();
                    source.selectedIndex = [...source.options].indexOf(option);
                    search.value = option.textContent.trim();
                    results.style.display = 'none';
                    recalculate();
                };
                button.addEventListener('mousedown', chooseOption);
                button.addEventListener('click', chooseOption);
                results.appendChild(button);
            });
            results.style.display = options.length ? 'block' : 'none';
        };
        type.addEventListener('change', () => { source.value = ''; search.value = ''; sync(row); });
        search.addEventListener('input', renderResults);
        search.addEventListener('focus', renderResults);
        source.addEventListener('change', recalculate);
        row.querySelector('input[name$="[quantity]"]').addEventListener('input', recalculate);
        row.querySelectorAll('[data-manual-wrap] input, [data-manual-price-wrap] input').forEach((input) => input.addEventListener('input', recalculate));
        row.querySelector('[data-remove-item]')?.addEventListener('click', () => { row.remove(); recalculate(); });
        document.addEventListener('click', (event) => { if (!results.contains(event.target) && event.target !== search) results.style.display = 'none'; });
        sync(row);
    };
    const customerSelect = document.getElementById('customer_id');
    const customerSearch = document.getElementById('customer-search');
    const customerResults = document.getElementById('customer-results');
    const renderCustomers = () => {
        customerResults.replaceChildren();
        const query = customerSearch.value.trim().toLocaleLowerCase('pt-BR');
        if (!query) { customerResults.style.display = 'none'; return; }
        const matches = [...customerSelect.options].filter((option) => option.value && (option.dataset.search || '').includes(query)).slice(0, 30);
        matches.forEach((option) => {
            const button = document.createElement('button');
            button.type = 'button'; button.textContent = option.textContent.trim();
            button.style.cssText = 'display:block;width:100%;text-align:left;padding:9px 12px;border:0;background:white;cursor:pointer';
            button.addEventListener('click', () => { customerSelect.value = option.value; customerSearch.value = option.textContent.trim(); customerResults.style.display = 'none'; });
            customerResults.appendChild(button);
        });
        customerResults.style.display = matches.length ? 'block' : 'none';
    };
    if (customerSelect.selectedOptions[0]?.value) customerSearch.value = customerSelect.selectedOptions[0].textContent.trim();
    customerSearch.addEventListener('input', () => { customerSelect.value = ''; renderCustomers(); });
    customerSearch.addEventListener('focus', renderCustomers);
    document.addEventListener('click', (event) => { if (!customerResults.contains(event.target) && event.target !== customerSearch) customerResults.style.display = 'none'; });
    const indexes = Array.from(list.querySelectorAll('[name^="items["]')).map((field) => Number(field.name.match(/^items\[(\d+)\]/)?.[1] ?? -1));
    let nextIndex = Math.max(-1, ...indexes) + 1;
    list.querySelectorAll('.quote-item').forEach(attachRow);
    document.getElementById('add-quote-item').addEventListener('click', () => {
        const fragment = template.content.cloneNode(true);
        fragment.querySelectorAll('[name]').forEach((field) => { field.name = field.name.replaceAll('__INDEX__', String(nextIndex++)); });
        list.appendChild(fragment);
        attachRow(list.lastElementChild);
    });
    recalculate();
})();
</script>
@endsection
