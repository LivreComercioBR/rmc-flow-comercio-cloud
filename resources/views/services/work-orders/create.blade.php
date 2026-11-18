@extends('layouts.app', ['title' => 'Nova ordem de serviço | RMC Flow Comércio Cloud'])

@section('content')
<section class="card">
    <p class="muted">MÓDULO DE SERVIÇOS</p>
    <h1 class="title">Nova ordem de serviço</h1>
    <p class="muted">Registre o cliente, o veículo ou equipamento, o problema relatado e os itens do atendimento.</p>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($customers->isEmpty())
        <div class="alert alert-warming">Cadastre um cliente antes de abrir uma OS. <a href="{{ route('customers.create') }}">Cadastrar cliente</a></div>
    @else
    <form method="post" action="{{ route('services.work-orders.store') }}" id="work-order-create-form">
        @csrf
        <div class="grid">
            <div class="field" style="position:relative">
                <label for="customer_search">Buscar cliente *</label>
                <input id="customer_search" type="search" placeholder="Digite o nome, documento ou telefone" autocomplete="off" aria-controls="customer-results" aria-autocomplete="list">
                <input id="customer_id" type="hidden" name="customer_id" value="{{ old('customer_id') }}" required>
                <div id="selected-customer" class="muted" style="min-height:22px;margin-top:6px">Nenhum cliente selecionado.</div>
                <div id="customer-results" role="listbox" style="display:none;position:absolute;z-index:20;top:100%;left:0;right:0;max-height:260px;overflow:auto;background:#fff;border:1px solid #cbd5e1;border-radius:8px;box-shadow:0 8px 24px #0b1f3a22"></div>
                <div class="actions" style="margin-top:8px"><a class="btn secondary" href="{{ route('customers.create') }}">Cadastrar novo cliente</a></div>
                <div id="customer-data" hidden>
                    @foreach ($customers as $customer)
                        <button type="button" data-id="{{ $customer->id }}" data-name="{{ $customer->name }}" data-search="{{ mb_strtolower($customer->name.' '.$customer->document.' '.$customer->phone) }}"></button>
                    @endforeach
                </div>
            </div>
            <div class="field">
                <label for="service_asset_id">Veículo / equipamento cadastrado</label>
                <select id="service_asset_id" name="service_asset_id">
                    <option value="">Selecione um veículo/equipamento cadastrado</option>
                    @foreach ($assets as $asset)
                        <option value="{{ $asset->id }}" data-customer="{{ $asset->customer_id }}" @selected((string) old('service_asset_id') === (string) $asset->id)>
                            {{ $asset->name }} · {{ $asset->identifier ?: 'sem identificação' }} · {{ $asset->customer?->name }}
                        </option>
                    @endforeach
                </select>
                <button class="btn secondary" type="button" id="add-asset-fields" style="margin-top:8px">Cadastrar / informar outro bem</button>
                <small class="muted">A lista será filtrada pelo cliente selecionado.</small>
            </div>
        </div>

        <fieldset id="new-asset-fields" style="border:1px solid #dbe3ed;border-radius:10px;padding:14px;margin:8px 0 20px">
            <legend>Equipamento / veículo atendido</legend>
            <div class="grid">
                <div class="field">
                    <label for="asset_type">Tipo</label>
                    <select id="asset_type" name="asset_type">
                        <option value="vehicle">Veículo</option><option value="motorcycle">Motocicleta</option><option value="equipment" selected>Equipamento</option><option value="other">Outro</option>
                    </select>
                </div>
                <div class="field"><label for="asset_description">Nome / descrição</label><input id="asset_description" name="asset_description" maxlength="180" placeholder="Ex.: iPhone 13, Honda Civic"></div>
                <div class="field"><label for="asset_identifier">Placa, série ou identificação</label><input id="asset_identifier" name="asset_identifier" maxlength="80"></div>
                <div class="field"><label for="asset_manufacturer">Fabricante</label><input id="asset_manufacturer" name="asset_manufacturer" maxlength="120"></div>
                <div class="field"><label for="asset_model">Modelo</label><input id="asset_model" name="asset_model" maxlength="120"></div>
                <div class="field"><label for="asset_year">Ano</label><input id="asset_year" name="asset_year" type="number" min="1900" max="{{ now()->year + 2 }}"></div>
                <div class="field"><label for="asset_color">Cor</label><input id="asset_color" name="asset_color" maxlength="60"></div>
            </div>
            <label style="display:flex;gap:8px;align-items:center;margin-top:10px"><input type="checkbox" name="save_asset" value="1" checked> Salvar também no cadastro do cliente para reutilizar e consultar o histórico</label>
        </fieldset>

        <div class="grid">
            <div class="field"><label for="initial_status">Status inicial *</label><select id="initial_status" name="initial_status" required><option value="open" @selected(old('initial_status', 'open') === 'open')>Aberta</option><option value="diagnosis" @selected(old('initial_status') === 'diagnosis')>Em diagnóstico</option><option value="in_progress" @selected(old('initial_status') === 'in_progress')>Em execução</option></select><small class="muted">Os demais status são aplicados conforme o andamento da OS.</small></div>
            <div class="field"><label for="expected_at">Previsão de entrega</label><input id="expected_at" name="expected_at" type="date" min="{{ now()->toDateString() }}" value="{{ old('expected_at') }}"></div>
            <div class="field"><label for="warranty_days">Garantia (dias)</label><input id="warranty_days" name="warranty_days" type="number" min="0" max="3650" value="{{ old('warranty_days', 0) }}"></div>
            <div class="field"><label for="mileage">Quilometragem (opcional)</label><input id="mileage" name="mileage" maxlength="80"></div>
            <div class="field"><label for="fuel_level">Combustível (opcional)</label><input id="fuel_level" name="fuel_level" maxlength="80"></div>
        </div>

        <div class="field" style="margin-top:16px"><label for="complaint">Problema relatado *</label><textarea id="complaint" name="complaint" maxlength="4000" rows="7" style="width:100%;min-height:160px;resize:vertical" required>{{ old('complaint') }}</textarea></div>
        <div class="field"><label for="diagnosis">Diagnóstico</label><textarea id="diagnosis" name="diagnosis" maxlength="4000" rows="6" style="width:100%;min-height:140px;resize:vertical">{{ old('diagnosis') }}</textarea></div>
        <div class="field"><label for="notes">Observações</label><textarea id="notes" name="notes" maxlength="4000" rows="6" style="width:100%;min-height:140px;resize:vertical">{{ old('notes') }}</textarea></div>

        <div class="actions" style="justify-content:space-between;margin-top:24px">
            <h2 style="margin:0">Serviços e peças</h2>
            <button class="btn secondary" type="button" id="add-work-order-item">Adicionar item</button>
        </div>
        <p class="muted">Peças são baixadas do estoque quando a OS avança para serviço concluído. O valor final gera a conta a receber quando você avança para aguardando pagamento.</p>
        <div id="work-order-items" style="display:grid;gap:10px"></div>
        <template id="work-order-item-template">
            <div class="work-order-item company" style="display:grid;grid-template-columns:150px minmax(180px,1fr) minmax(150px,1fr) 120px 140px auto;gap:10px;align-items:end;margin:0">
                <div class="field" style="margin:0"><label>Tipo</label><select class="item-type" name="items[__INDEX__][type]"><option value="service">Serviço</option><option value="product">Produto / peça</option></select></div>
                <div class="field item-name-wrap" style="margin:0"><label>Serviço</label><input name="items[__INDEX__][name]" maxlength="180" placeholder="Ex.: Troca de tela"></div>
                <div class="field item-product-wrap" style="margin:0" hidden><label>Produto / peça</label><select name="items[__INDEX__][product_id]"><option value="">Selecione</option>@foreach ($products as $product)<option value="{{ $product->id }}" data-price="{{ number_format($product->sale_price_cents / 100, 2, '.', '') }}">{{ $product->name }} (saldo {{ $product->stock_quantity }} {{ $product->unit }})</option>@endforeach</select></div>
                <div class="field" style="margin:0"><label>Quantidade</label><input name="items[__INDEX__][quantity]" type="number" min="0.001" step="0.001" value="1.000"></div>
                <div class="field item-price-wrap" style="margin:0"><label>Valor unitário (R$)</label><input name="items[__INDEX__][unit_price]" type="number" min="0" step="0.01" value="0.00"></div>
                <button class="btn secondary remove-work-order-item" type="button">Remover</button>
                <div class="field item-description-wrap" style="grid-column:1/-2;margin:0"><label>Detalhe do item (opcional)</label><input name="items[__INDEX__][description]" maxlength="1000"></div>
            </div>
        </template>
        <div class="actions" style="margin-top:20px">
            <button class="btn" type="submit">Salvar OS</button>
            <a class="btn secondary" href="{{ route('services.work-orders.index') }}">Voltar</a>
        </div>
    </form>
    @endif
</section>
<script>
(() => {
    const customer = document.getElementById('customer_id');
    const customerSearch = document.getElementById('customer_search');
    const customerResults = document.getElementById('customer-results');
    const selectedCustomer = document.getElementById('selected-customer');
    const customerData = [...document.querySelectorAll('#customer-data [data-id]')];
    const chooseCustomer = (entry) => {
        customer.value = entry.dataset.id;
        customerSearch.value = entry.dataset.name;
        selectedCustomer.textContent = 'Cliente selecionado: ' + entry.dataset.name;
        customerResults.replaceChildren();
        customerResults.style.display = 'none';
        syncAssets();
    };
    const renderCustomers = () => {
        const term = customerSearch.value.trim().toLocaleLowerCase();
        customer.value = '';
        selectedCustomer.textContent = 'Selecione um resultado para vincular o cliente à OS.';
        customerResults.replaceChildren();
        if (!term) { customerResults.style.display = 'none'; syncAssets(); return; }
        const matches = customerData.filter(entry => entry.dataset.search.includes(term)).slice(0, 10);
        if (!matches.length) {
            const empty = document.createElement('div');
            empty.textContent = 'Nenhum cliente encontrado.';
            empty.style.cssText = 'padding:10px 12px;color:#66758b';
            customerResults.append(empty);
        } else {
            matches.forEach(entry => {
                const button = document.createElement('button');
                button.type = 'button';
                button.role = 'option';
                button.textContent = entry.dataset.name;
                button.style.cssText = 'display:block;width:100%;padding:10px 12px;text-align:left;border:0;border-bottom:1px solid #e2e8f0;background:white;font:inherit;cursor:pointer';
                button.addEventListener('click', () => chooseCustomer(entry));
                customerResults.append(button);
            });
        }
        customerResults.style.display = 'block';
        syncAssets();
    };
    customerSearch?.addEventListener('input', renderCustomers);
    customerSearch?.addEventListener('focus', () => { if (customerSearch.value && !customer.value) renderCustomers(); });
    const asset = document.getElementById('service_asset_id');
    document.getElementById('add-asset-fields')?.addEventListener('click', () => {
        asset.value = '';
        assetFields.hidden = false;
        description.required = true;
        description.focus();
    });
    const assetFields = document.getElementById('new-asset-fields');
    const description = document.getElementById('asset_description');
    const syncAssets = () => {
        const customerId = customer.value;
        [...asset.options].forEach(option => {
            if (!option.value) { option.hidden = false; return; }
            option.hidden = option.dataset.customer !== customerId;
            if (option.hidden && option.selected) asset.value = '';
        });
        assetFields.hidden = Boolean(asset.value);
        description.required = !asset.value;
    };
    customer?.addEventListener('change', syncAssets);
    asset?.addEventListener('change', syncAssets);
    syncAssets();
    const initialCustomer = customerData.find(entry => entry.dataset.id === customer.value);
    if (initialCustomer) chooseCustomer(initialCustomer);

    const list = document.getElementById('work-order-items');
    const template = document.getElementById('work-order-item-template');
    let next = 0;
    const syncItem = row => {
        const productMode = row.querySelector('.item-type').value === 'product';
        row.querySelector('.item-name-wrap').hidden = productMode;
        row.querySelector('.item-product-wrap').hidden = !productMode;
        row.querySelector('.item-price-wrap').hidden = productMode;
        row.querySelector('[name$="[name]"]').required = !productMode;
        row.querySelector('[name$="[product_id]"]').required = productMode;
        row.querySelector('[name$="[quantity]"]').required = true;
    };
    document.getElementById('add-work-order-item')?.addEventListener('click', () => {
        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(next++)));
        const row = list.lastElementChild;
        row.querySelector('.item-type').addEventListener('change', () => syncItem(row));
        row.querySelector('.remove-work-order-item').addEventListener('click', () => row.remove());
        syncItem(row);
    });
})();
</script>
@endsection
