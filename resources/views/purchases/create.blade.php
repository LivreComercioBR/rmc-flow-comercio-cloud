@extends('layouts.app', ['title' => 'Registrar compra | RMC Flow Comércio Cloud'])
@section('content')
<section class="card">
    <p class="muted" style="margin-bottom:8px">FORNECIMENTO E ESTOQUE</p><h1 class="title">Registrar compra</h1><p class="muted">A confirmação adicionará os itens ao estoque e criará as parcelas em contas a pagar.</p>
    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @if ($suppliers->isEmpty())<div class="alert alert-warming">Cadastre um fornecedor antes de registrar uma compra. <a href="{{ route('suppliers.create') }}">Cadastrar fornecedor</a></div>@endif
    @if ($products->isEmpty())<div class="alert alert-warming">Cadastre ao menos um produto ou peça antes de registrar uma compra. <a href="{{ route('products.create', ['purchase_popup' => 1]) }}" target="_blank" rel="opener">Cadastrar produto</a></div>@endif
    <form method="post" action="{{ route('purchases.store') }}" id="purchase-form">@csrf
        <input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">
        <div class="grid">
            <div class="field"><label for="supplier_id">Fornecedor *</label><select id="supplier_id" name="supplier_id" required style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit"><option value="">Selecione</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string)old('supplier_id', $supplierId) === (string)$supplier->id)>{{ $supplier->name }}</option>@endforeach</select><a href="{{ route('suppliers.create', ['return_to' => 'purchases.create']) }}">Cadastrar fornecedor e voltar à compra</a>@error('supplier_id')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="invoice_number">Número da nota / documento</label><input id="invoice_number" name="invoice_number" maxlength="64" value="{{ old('invoice_number') }}">@error('invoice_number')<div class="error">{{ $message }}</div>@enderror</div>
        </div>
        <div class="grid"><div class="field"><label for="purchased_at">Data da compra *</label><input id="purchased_at" name="purchased_at" type="date" required value="{{ old('purchased_at', now()->toDateString()) }}">@error('purchased_at')<div class="error">{{ $message }}</div>@enderror</div></div>
        <div class="actions" style="justify-content:space-between;align-items:center;margin-top:24px"><h2 style="margin:0">Itens</h2><a class="btn secondary" href="{{ route('products.create', ['purchase_popup' => 1]) }}" target="_blank" rel="opener">Cadastrar produto / peça</a></div>
        @error('items')<div class="alert alert-danger">{{ $message }}</div>@enderror
        <div id="purchase-items" style="display:grid;gap:10px">
            @foreach (old('items', [['product_id'=>'','quantity'=>'1.000','unit_cost'=>'0.00']]) as $index => $item)
                <div class="purchase-item" style="display:grid;grid-template-columns:minmax(220px,2fr) minmax(110px,1fr) minmax(130px,1fr) auto;gap:10px;align-items:end;border:1px solid #e1e8f0;border-radius:10px;padding:12px">
                    <div class="field product-picker" style="margin:0"><label>Buscar produto por nome, SKU ou código de barras</label><input type="search" class="product-search" placeholder="Digite nome, SKU ou código" autocomplete="off" style="width:100%;height:38px;border:1px solid #cbd5e1;border-radius:8px;padding:0 8px;font:inherit"><label>Produto *</label><select name="items[{{ $index }}][product_id]" required style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:8px;padding:0 8px;font:inherit"><option value="">Selecione</option>@foreach ($products as $product)<option value="{{ $product->id }}" data-search="{{ $product->name }} {{ $product->sku }} {{ $product->barcode }}" @selected((string)($item['product_id'] ?? '') === (string)$product->id)>{{ $product->name }} (saldo {{ $product->stock_quantity }} {{ $product->unit }})</option>@endforeach</select></div>
                    <div class="field" style="margin:0"><label>Quantidade *</label><input name="items[{{ $index }}][quantity]" type="number" min="0.001" max="999999999.999" step="0.001" required value="{{ $item['quantity'] ?? '1.000' }}" style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:8px;padding:0 8px;font:inherit"></div>
                    <div class="field" style="margin:0"><label>Custo unitário (R$) *</label><input name="items[{{ $index }}][unit_cost]" type="number" min="0" max="42949672.95" step="0.01" required value="{{ $item['unit_cost'] ?? '0.00' }}" style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:8px;padding:0 8px;font:inherit"></div>
                    <button class="btn secondary remove-item" type="button" aria-label="Remover item">Remover</button>
                </div>
            @endforeach
        </div>
        @foreach ($errors->getMessages() as $key => $messages) @if (str_starts_with($key, 'items.'))<div class="error">{{ $messages[0] }}</div>@endif @endforeach
        <button class="btn secondary" type="button" id="add-item" style="margin:12px 0 20px">Adicionar item</button>
        <section class="company" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin:4px 0 20px">
            <div><span class="muted">Valor total da compra</span><strong id="purchase-total" style="display:block;font-size:22px">R$ 0,00</strong></div>
            <div><span class="muted">Soma das parcelas</span><strong id="installment-total" style="display:block;font-size:22px">R$ 0,00</strong><small id="installment-balance" class="muted">Informe os produtos para calcular os valores.</small></div>
        </section>
        <div class="grid">
            <div class="field"><label for="payment_method">Forma acordada com o fornecedor *</label><select id="payment_method" name="payment_method" required style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit">
                @foreach (['supplier_terms' => 'Prazo / acordo com fornecedor', 'boleto' => 'Boleto', 'pix' => 'PIX', 'bank_transfer' => 'Transferência bancária', 'cash' => 'Dinheiro', 'debit_card' => 'Cartão de débito', 'credit_card' => 'Cartão de crédito', 'other' => 'Outro'] as $method => $label)
                    <option value="{{ $method }}" @selected(old('payment_method', 'supplier_terms') === $method)>{{ $label }}</option>
                @endforeach
            </select><small class="muted">A compra será lançada em contas a pagar. Registre a baixa quando o pagamento ocorrer.</small>@error('payment_method')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="installment_count">Número de parcelas *</label><input id="installment_count" name="installment_count" type="number" min="1" max="36" required value="{{ old('installment_count', 1) }}">@error('installment_count')<div class="error">{{ $message }}</div>@enderror</div>
        </div>
        <div class="field" style="margin:10px 0 20px"><span class="label">Valores e vencimentos das parcelas *</span><p class="muted" style="margin:0">Defina o valor e a data de cada parcela. A soma precisa fechar com o total da compra; as datas começam com sugestão mensal e podem ser ajustadas.</p><div id="purchase-due-dates" class="grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-top:10px"></div>@error('due_dates')<div class="error">{{ $message }}</div>@enderror @error('installment_amounts')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="notes">Observações</label><textarea id="notes" name="notes" rows="3" maxlength="5000" style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:10px;font:inherit">{{ old('notes') }}</textarea>@error('notes')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="actions"><button class="btn" type="submit" @disabled($suppliers->isEmpty() || $products->isEmpty())>Registrar compra</button><a class="btn secondary" href="{{ route('purchases.index') }}">Voltar</a></div>
    </form>
    <template id="item-template"><div class="purchase-item" style="display:grid;grid-template-columns:minmax(220px,2fr) minmax(110px,1fr) minmax(130px,1fr) auto;gap:10px;align-items:end;border:1px solid #e1e8f0;border-radius:10px;padding:12px"><div class="field product-picker" style="margin:0"><label>Buscar produto por nome, SKU ou código de barras</label><input type="search" class="product-search" placeholder="Digite nome, SKU ou código" autocomplete="off" style="width:100%;height:38px;border:1px solid #cbd5e1;border-radius:8px;padding:0 8px;font:inherit"><label>Produto *</label><select name="items[__INDEX__][product_id]" required style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:8px;padding:0 8px;font:inherit"><option value="">Selecione</option>@foreach ($products as $product)<option value="{{ $product->id }}" data-search="{{ $product->name }} {{ $product->sku }} {{ $product->barcode }}">{{ $product->name }} (saldo {{ $product->stock_quantity }} {{ $product->unit }})</option>@endforeach</select></div><div class="field" style="margin:0"><label>Quantidade *</label><input name="items[__INDEX__][quantity]" type="number" min="0.001" max="999999999.999" step="0.001" required value="1.000" style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:8px;padding:0 8px;font:inherit"></div><div class="field" style="margin:0"><label>Custo unitário (R$) *</label><input name="items[__INDEX__][unit_cost]" type="number" min="0" max="42949672.95" step="0.01" required value="0.00" style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:8px;padding:0 8px;font:inherit"></div><button class="btn secondary remove-item" type="button">Remover</button></div></template>
</section>
<script>
(() => {
  const list = document.getElementById('purchase-items');
  const template = document.getElementById('item-template');
  let nextIndex = {{ count(old('items', [['product_id' => '', 'quantity' => '1.000', 'unit_cost' => '0.00']])) }};
  const filterProductOptions = (input) => {
    const select = input.closest('.product-picker')?.querySelector('select');
    if (!select) return;
    const query = input.value.trim().toLocaleLowerCase('pt-BR');
    [...select.options].forEach(option => {
      if (!option.value) { option.hidden = false; return; }
      const haystack = (option.dataset.search || option.textContent).toLocaleLowerCase('pt-BR');
      option.hidden = Boolean(query) && !haystack.includes(query);
    });
  };
  list.addEventListener('input', event => {
    if (event.target.matches('.product-search')) filterProductOptions(event.target);
  });
  list.addEventListener('change', event => {
    if (event.target.matches('select[name$="[product_id]"]')) {
      const search = event.target.closest('.product-picker')?.querySelector('.product-search');
      if (search) { search.value = ''; filterProductOptions(search); }
    }
  });
  document.getElementById('add-item').addEventListener('click', () => {
    const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
    list.insertAdjacentHTML('beforeend', html);
  });
  list.addEventListener('click', (event) => {
    const button = event.target.closest('.remove-item');
    if (button && list.querySelectorAll('.purchase-item').length > 1) {
      button.closest('.purchase-item').remove();
      totalCents = getPurchaseTotalCents();
      if (!customAmounts) setDefaultAmounts();
      updateTotals();
    }
  });
  const installmentCount = document.getElementById('installment_count');
  const dueDates = document.getElementById('purchase-due-dates');
  const totalDisplay = document.getElementById('purchase-total');
  const installmentDisplay = document.getElementById('installment-total');
  const balanceDisplay = document.getElementById('installment-balance');
  const submitButton = document.querySelector('#purchase-form button[type="submit"]');
  const oldDueDates = @json(old('due_dates', []));
  const oldAmounts = @json(old('installment_amounts', []));
  let firstDueDate = oldDueDates[0] || @json(old('first_due_date', now()->toDateString()));
  let customAmounts = oldAmounts.length > 0;
  let amountInputs = [];
  let dateInputs = [];
  let totalCents = 0;
  const money = cents => 'R$ ' + (cents / 100).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
  const datePlusMonths = (value, months) => {
    const [year, month, day] = value.split('-').map(Number);
    const target = new Date(year, month - 1 + months, 1);
    const lastDay = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate();
    target.setDate(Math.min(day, lastDay));
    return [target.getFullYear(), String(target.getMonth() + 1).padStart(2, '0'), String(target.getDate()).padStart(2, '0')].join('-');
  };
  const getPurchaseTotalCents = () => [...list.querySelectorAll('.purchase-item')].reduce((sum, row) => {
    const quantity = Number(row.querySelector('input[name$="[quantity]"]')?.value || 0);
    const unitCents = Math.round(Number(row.querySelector('input[name$="[unit_cost]"]')?.value || 0) * 100);
    const quantityMilli = Math.round(quantity * 1000);
    return sum + Math.round(quantityMilli * unitCents / 1000);
  }, 0);
  const setDefaultAmounts = () => {
    const count = amountInputs.length;
    if (!count || customAmounts) return;
    const base = Math.floor(totalCents / count);
    const remainder = totalCents % count;
    amountInputs.forEach((input, index) => {
      input.value = ((base + (index < remainder ? 1 : 0)) / 100).toFixed(2);
    });
  };
  const updateTotals = () => {
    totalCents = getPurchaseTotalCents();
    totalDisplay.textContent = money(totalCents);
    const sum = amountInputs.reduce((value, input) => value + Math.round(Number(input.value || 0) * 100), 0);
    installmentDisplay.textContent = money(sum);
    const difference = totalCents - sum;
    if (totalCents <= 0) {
      balanceDisplay.textContent = 'Informe os produtos e os custos para calcular as parcelas.';
      balanceDisplay.className = 'muted';
    } else if (difference === 0) {
      balanceDisplay.textContent = 'Valores conferidos: as parcelas fecham com o total da compra.';
      balanceDisplay.className = 'muted';
    } else {
      balanceDisplay.textContent = difference > 0 ? 'Faltam ' + money(difference) + ' para fechar o total.' : 'As parcelas excedem o total em ' + money(-difference) + '.';
      balanceDisplay.className = 'error';
    }
    const hasSupplier = Boolean(document.getElementById('supplier_id').value);
    const hasAvailableProduct = template.content.querySelector('select')?.options.length > 1;
    submitButton.disabled = !hasSupplier || !hasAvailableProduct || totalCents <= 0 || difference !== 0;
  };
  const renderDueDates = () => {
    const count = Math.min(36, Math.max(1, Number(installmentCount.value) || 1));
    const previousDates = dateInputs.map(input => input.value);
    const previousAmounts = amountInputs.map(input => input.value);
    dueDates.replaceChildren();
    amountInputs = [];
    dateInputs = [];
    for (let i = 0; i < count; i++) {
      const card = document.createElement('div');
      card.className = 'company';
      card.style.cssText = 'display:grid;grid-template-columns:1fr;gap:8px;margin:0;padding:12px';
      const title = document.createElement('strong');
      title.textContent = 'Parcela ' + (i + 1);
      const amountLabel = document.createElement('label');
      amountLabel.htmlFor = 'purchase_installment_amount_' + i;
      amountLabel.textContent = 'Valor (R$) *';
      const amount = document.createElement('input');
      amount.type = 'number';
      amount.id = amountLabel.htmlFor;
      amount.name = 'installment_amounts[' + i + ']';
      amount.min = '0.01';
      amount.max = '42949672.95';
      amount.step = '0.01';
      amount.required = true;
      amount.value = previousAmounts[i] ?? oldAmounts[i] ?? '0.00';
      amount.addEventListener('input', () => { customAmounts = true; updateTotals(); });
      const dateLabel = document.createElement('label');
      dateLabel.htmlFor = 'purchase_due_date_' + i;
      dateLabel.textContent = 'Vencimento *';
      const date = document.createElement('input');
      date.type = 'date';
      date.id = dateLabel.htmlFor;
      date.name = 'due_dates[' + i + ']';
      date.required = true;
      date.value = previousDates[i] || oldDueDates[i] || datePlusMonths(firstDueDate, i);
      date.addEventListener('change', () => {
        if (i === 0) {
          firstDueDate = date.value;
          dateInputs.forEach((other, index) => {
            if (index > 0 && !other.dataset.edited) other.value = datePlusMonths(firstDueDate, index);
          });
        }
        date.dataset.edited = 'true';
      });
      amountInputs.push(amount);
      dateInputs.push(date);
      card.append(title, amountLabel, amount, dateLabel, date);
      dueDates.append(card);
    }
    setDefaultAmounts();
    updateTotals();
  };
  installmentCount.addEventListener('input', () => {
    customAmounts = oldAmounts.length > 0;
    renderDueDates();
  });
  list.addEventListener('input', () => {
    totalCents = getPurchaseTotalCents();
    if (!customAmounts) setDefaultAmounts();
    updateTotals();
  });
  list.addEventListener('change', updateTotals);
  document.getElementById('supplier_id').addEventListener('change', updateTotals);
  renderDueDates();
  window.addEventListener('message', (event) => {
    if (event.origin !== window.location.origin || event.data?.type !== 'rmc-product-created-for-purchase') return;
    const product = event.data.product;
    if (!product?.id || !product?.name) return;
    const optionLabel = `${product.name} (saldo ${product.stock_quantity} ${product.unit})`;
    const selects = [...list.querySelectorAll('select[name$="[product_id]"]'), template.content.querySelector('select')];
    selects.forEach((select) => {
      if (![...select.options].some(option => option.value === String(product.id))) {
        select.add(new Option(optionLabel, String(product.id)));
      }
    });
    let target = [...list.querySelectorAll('select[name$="[product_id]"]')].find(select => !select.value);
    if (!target) {
      document.getElementById('add-item').click();
      target = [...list.querySelectorAll('select[name$="[product_id]"]')].at(-1);
    }
    target.value = String(product.id);
    target.dispatchEvent(new Event('change', { bubbles: true }));
    updateTotals();
    target.focus();
  });


})();
</script>

@if(request()->boolean('purchase_popup') && $newlyCreatedProduct)
<script>
if (window.opener && !window.opener.closed) {
    window.opener.postMessage({
        type: 'rmc-product-created-for-purchase',
        product: {
            id: @json($newlyCreatedProduct->id),
            name: @json($newlyCreatedProduct->name),
            unit: @json($newlyCreatedProduct->unit),
            stock_quantity: @json($newlyCreatedProduct->stock_quantity),
        },
    }, window.location.origin);
    window.setTimeout(() => window.close(), 400);
}
</script>
@endif
@endsection
