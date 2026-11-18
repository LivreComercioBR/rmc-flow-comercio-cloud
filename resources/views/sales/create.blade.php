@extends('layouts.app', ['title' => 'Nova venda | RMC Flow Comércio Cloud'])
@section('content')
<section class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
        <div><p class="muted" style="margin-bottom:8px">PDV</p><h1 class="title">Registrar venda</h1><p class="muted">Pesquise e adicione somente os produtos que o cliente está comprando.</p></div>
        <div class="actions"><a class="btn secondary" href="{{ route('sales.index') }}">Vendas</a><a class="btn secondary" href="{{ route('cash.index') }}">Caixa</a></div>
    </div>

    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @if ($products->isEmpty())
        <div class="alert alert-warming">Cadastre produtos ativos com estoque para iniciar uma venda.</div>
    @else
    <form method="post" action="{{ route('sales.store') }}" id="sale-form">
        @csrf
        <input type="hidden" name="request_key" value="{{ $requestKey }}">

        <div class="field" style="max-width:620px">
            <label for="customer_id">Cliente (obrigatório para venda a prazo)</label>
            <select id="customer_id" name="customer_id" style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit">
                <option value="">Consumidor</option>
                @foreach ($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id', $customerId) == $customer->id)>{{ $customer->name }}</option>@endforeach
            </select>
        </div>

        <h2 style="margin-top:22px">Adicionar produtos</h2>
        <div class="field" style="max-width:720px;position:relative">
            <label for="product_search">Pesquisar por produto, SKU ou código de barras</label>
            <input type="search" id="product_search" autocomplete="off" placeholder="Digite o nome ou código do produto" style="width:100%;height:46px;border:1px solid #cbd5e1;border-radius:8px;padding:0 12px;font:inherit">
            <div id="product_results" role="listbox" aria-label="Resultados da pesquisa" style="display:none;position:absolute;z-index:5;top:72px;left:0;right:0;max-height:300px;overflow:auto;background:#fff;border:1px solid #cbd5e1;border-radius:8px;box-shadow:0 8px 24px #0a244022"></div>
            <small class="muted" id="search_hint" aria-live="polite">Comece a digitar para localizar produtos disponíveis.</small>
        </div>

        <h2 style="margin-top:22px">Itens da venda</h2>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;text-align:left;min-width:620px">
                <thead><tr><th style="padding:9px;border-bottom:1px solid #dbe3ed">Produto</th><th style="padding:9px;border-bottom:1px solid #dbe3ed">Disponível</th><th style="padding:9px;border-bottom:1px solid #dbe3ed">Preço</th><th style="padding:9px;border-bottom:1px solid #dbe3ed">Quantidade</th><th style="padding:9px;border-bottom:1px solid #dbe3ed">Total</th><th style="padding:9px;border-bottom:1px solid #dbe3ed">Ação</th></tr></thead>
                <tbody id="cart_items"></tbody>
            </table>
        </div>
        <p id="empty_cart" class="alert alert-warming" style="margin-top:12px">Nenhum produto adicionado. Pesquise acima para montar a venda.</p>
        <div id="cart_errors" class="error" role="alert" style="margin-top:8px"></div>

        <h2 style="margin-top:26px">Recebimentos no ato</h2>
        <p class="muted">Divida o pagamento em até três formas. Qualquer saldo restante ficará em contas a receber.</p>
        <div class="grid" style="max-width:1000px">
            @for ($i = 0; $i < 3; $i++)
            <div class="field">
                <label for="method_{{ $i }}">Forma {{ $i + 1 }}</label>
                <select name="payments[{{ $i }}][method]" id="method_{{ $i }}" style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit">
                    <option value="">Não informado</option><option value="cash">Dinheiro</option><option value="pix">PIX</option><option value="debit_card">Cartão de débito</option><option value="credit_card">Cartão de crédito</option><option value="bank_transfer">Transferência</option><option value="other">Outro</option>
                </select>
                <input type="number" name="payments[{{ $i }}][amount]" min="0" step="0.01" placeholder="Valor em R$" value="{{ old('payments.'.$i.'.amount') }}" style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit;margin-top:4px">
                <input type="text" name="payments[{{ $i }}][reference]" maxlength="120" placeholder="Referência (opcional)" value="{{ old('payments.'.$i.'.reference') }}" style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit;margin-top:4px">
            </div>
            @endfor
        </div>

        <section aria-labelledby="sale-summary-heading" style="max-width:720px;margin-top:14px;border:1px solid #dbe3ed;border-radius:10px;padding:16px">
            <h2 id="sale-summary-heading" style="margin:0 0 14px">Resumo e desconto</h2>
            <div style="display:flex;justify-content:space-between;gap:16px;margin:8px 0"><span>Subtotal dos itens</span><strong>R$ <span id="subtotal_display">0,00</span></strong></div>
            <div class="field" style="margin:12px 0"><label for="discount">Desconto geral (R$)</label><input type="number" id="discount" name="discount" min="0" step="0.01" value="{{ old('discount', '0.00') }}" style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 12px;font:inherit"><small class="muted">Informe o desconto depois de selecionar os produtos e as formas de pagamento.</small></div>
            <div style="display:flex;justify-content:space-between;gap:16px;margin:8px 0;font-size:18px"><strong>Total da venda</strong><strong>R$ <span id="total_display">0,00</span></strong></div>
            <div style="display:flex;justify-content:space-between;gap:16px;margin:8px 0"><span>Recebido no ato</span><strong>R$ <span id="paid_display">0,00</span></strong></div>
            <div style="display:flex;justify-content:space-between;gap:16px;margin:8px 0"><span>Saldo a receber</span><strong>R$ <span id="balance_display">0,00</span></strong></div>
            <div id="discount_error" class="error" role="alert"></div>
        </section>

        <div class="grid" style="max-width:720px;margin-top:16px">
            <div class="field"><label for="installments">Parcelas do saldo em aberto</label><select name="installments" id="installments" style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit">@for ($i = 1; $i <= 12; $i++)<option value="{{ $i }}" @selected(old('installments', 1) == $i)>{{ $i }}x</option>@endfor</select></div>
            <div class="field"><label for="first_due_date">Vencimento da primeira parcela</label><input type="date" id="first_due_date" name="first_due_date" min="{{ now()->toDateString() }}" value="{{ old('first_due_date', now()->toDateString()) }}" style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit"></div>
        </div>
        <div class="actions" style="margin-top:22px"><button class="btn" id="confirm_sale" type="submit">Confirmar venda</button><a class="btn secondary" href="{{ route('sales.index') }}">Cancelar</a></div>
    </form>
    @endif
</section>

@if (! $products->isEmpty())
<script>
(() => {
    const products = {{ Illuminate\Support\Js::from($products->map(fn ($product) => ['id' => (int) $product->id, 'name' => $product->name, 'sku' => $product->sku, 'barcode' => $product->barcode, 'stock' => (float) $product->stock_quantity, 'price' => (int) $product->sale_price_cents])->values()) }};
    const previousItems = {{ Illuminate\Support\Js::from(old('items', [])) }};
    const cart = new Map();
    const results = document.getElementById('product_results');
    const search = document.getElementById('product_search');
    const hint = document.getElementById('search_hint');
    const body = document.getElementById('cart_items');
    const empty = document.getElementById('empty_cart');
    const discount = document.getElementById('discount');
    const money = cents => (cents / 100).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const formatCents = value => Math.max(0, Math.round((Number(String(value || '0').replace(',', '.')) || 0) * 100));

    function renderCart() {
        body.replaceChildren();
        let subtotal = 0;
        let index = 0;
        for (const [id, quantity] of cart) {
            const product = products.find(item => item.id === id);
            if (!product) continue;
            const line = product.price * quantity;
            subtotal += line;
            const row = document.createElement('tr');
            row.style.borderBottom = '1px solid #edf1f5';
            const name = document.createElement('td'); name.style.padding = '10px';
            const strong = document.createElement('strong'); strong.textContent = product.name; name.append(strong);
            const code = document.createElement('small'); code.className = 'muted'; code.style.display = 'block'; code.textContent = product.sku || product.barcode || ''; name.append(code);
            const available = document.createElement('td'); available.style.padding = '10px'; available.textContent = String(product.stock);
            const price = document.createElement('td'); price.style.padding = '10px'; price.textContent = 'R$ ' + money(product.price);
            const qtyCell = document.createElement('td'); qtyCell.style.padding = '10px';
            const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = `items[${index}][product_id]`; hidden.value = String(product.id); qtyCell.append(hidden);
            const qty = document.createElement('input'); qty.type = 'number'; qty.name = `items[${index}][quantity]`; qty.min = '1'; qty.max = String(product.stock); qty.step = '1'; qty.value = String(quantity); qty.setAttribute('aria-label', `Quantidade de ${product.name}`); qty.style.cssText = 'width:110px;height:40px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit';
            qty.addEventListener('change', () => { const val = Math.floor(Number(qty.value)); if (val > 0 && val <= product.stock) cart.set(product.id, val); else cart.delete(product.id); renderCart(); });
            qtyCell.append(qty);
            const total = document.createElement('td'); total.style.padding = '10px'; total.textContent = 'R$ ' + money(line);
            const action = document.createElement('td'); action.style.padding = '10px';
            const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn secondary'; remove.textContent = 'Remover'; remove.addEventListener('click', () => { cart.delete(product.id); renderCart(); }); action.append(remove);
            row.append(name, available, price, qtyCell, total, action); body.append(row); index++;
        }
        empty.style.display = cart.size ? 'none' : 'block';
        const discountCents = formatCents(discount.value);
        const paidCents = [...document.querySelectorAll('[name$="[amount]"]')].reduce((sum, input) => sum + formatCents(input.value), 0);
        const actualDiscount = Math.min(discountCents, subtotal);
        const totalCents = Math.max(0, subtotal - actualDiscount);
        document.getElementById('subtotal_display').textContent = money(subtotal);
        document.getElementById('total_display').textContent = money(totalCents);
        document.getElementById('paid_display').textContent = money(paidCents);
        document.getElementById('balance_display').textContent = money(Math.max(0, totalCents - paidCents));
        const discountError = document.getElementById('discount_error');
        discountError.textContent = discountCents >= subtotal && discountCents > 0 ? 'O desconto deve ser menor que o subtotal.' : '';
        document.getElementById('confirm_sale').disabled = cart.size === 0 || (discountCents >= subtotal && discountCents > 0);
    }

    function addProduct(id) {
        const product = products.find(item => item.id === id);
        if (!product) return;
        const next = (cart.get(id) || 0) + 1;
        if (next > product.stock) { hint.textContent = 'A quantidade adicionada atingiu o saldo disponível.'; return; }
        cart.set(id, next);
        search.value = '';
        results.replaceChildren();
        results.style.display = 'none';
        hint.textContent = product.name + ' adicionado à venda.';
        renderCart();
        search.focus();
    }

    function showResults() {
        const term = search.value.trim().toLocaleLowerCase('pt-BR');
        results.replaceChildren();
        if (!term) { results.style.display = 'none'; hint.textContent = 'Comece a digitar para localizar produtos disponíveis.'; return; }
        const found = products.filter(product => [product.name, product.sku, product.barcode].some(value => (value || '').toLocaleLowerCase('pt-BR').includes(term))).slice(0, 10);
        if (!found.length) { results.style.display = 'none'; hint.textContent = 'Nenhum produto encontrado com estoque disponível.'; return; }
        for (const product of found) {
            const button = document.createElement('button'); button.type = 'button'; button.setAttribute('role', 'option');
            button.style.cssText = 'display:flex;width:100%;justify-content:space-between;gap:12px;padding:12px;border:0;border-bottom:1px solid #edf1f5;background:white;text-align:left;font:inherit;cursor:pointer';
            const label = document.createElement('span'); label.textContent = product.name + (product.sku ? ' · ' + product.sku : '');
            const detail = document.createElement('strong'); detail.textContent = `R$ ${money(product.price)} · saldo ${product.stock}`;
            button.append(label, detail); button.addEventListener('click', () => addProduct(product.id)); results.append(button);
        }
        results.style.display = 'block'; hint.textContent = `${found.length} produto(s) encontrado(s).`;
    }

    search.addEventListener('input', showResults);
    document.addEventListener('click', event => { if (!results.contains(event.target) && event.target !== search) results.style.display = 'none'; });
    discount.addEventListener('input', renderCart);
    document.querySelectorAll('[name$="[amount]"]').forEach(input => input.addEventListener('input', renderCart));
    previousItems.forEach(item => { const id = Number(item.product_id); const quantity = Math.floor(Number(item.quantity)); if (products.some(product => product.id === id) && quantity > 0) cart.set(id, Math.min(quantity, products.find(product => product.id === id).stock)); });
    renderCart();
})();
</script>
@endif
@endsection
