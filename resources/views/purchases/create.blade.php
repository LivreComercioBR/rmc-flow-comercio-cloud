@extends('layouts.app', ['title' => 'Registrar compra | RMC Flow Comércio Cloud'])
@section('content')
<section class="card">
    <p class="muted" style="margin-bottom:8px">FORNECIMENTO E ESTOQUE</p><h1 class="title">Registrar compra</h1><p class="muted">A confirmação adicionará os itens ao estoque e criará as parcelas em contas a pagar.</p>
    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @if ($suppliers->isEmpty())<div class="alert alert-warming">Cadastre um fornecedor antes de registrar uma compra. <a href="{{ route('suppliers.create') }}">Cadastrar fornecedor</a></div>@endif
    @if ($products->isEmpty())<div class="alert alert-warming">Cadastre ao menos um produto ou peça antes de registrar uma compra. <a href="{{ route('products.create') }}">Cadastrar produto</a></div>@endif
    <form method="post" action="{{ route('purchases.store') }}" id="purchase-form">@csrf
        <input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">
        <div class="grid">
            <div class="field"><label for="supplier_id">Fornecedor *</label><select id="supplier_id" name="supplier_id" required style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit"><option value="">Selecione</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string)old('supplier_id', $supplierId) === (string)$supplier->id)>{{ $supplier->name }}</option>@endforeach</select><a href="{{ route('suppliers.create', ['return_to' => 'purchases.create']) }}">Cadastrar fornecedor e voltar à compra</a>@error('supplier_id')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="invoice_number">Número da nota / documento</label><input id="invoice_number" name="invoice_number" maxlength="64" value="{{ old('invoice_number') }}">@error('invoice_number')<div class="error">{{ $message }}</div>@enderror</div>
        </div>
        <div class="grid"><div class="field"><label for="purchased_at">Data da compra *</label><input id="purchased_at" name="purchased_at" type="date" required value="{{ old('purchased_at', now()->toDateString()) }}">@error('purchased_at')<div class="error">{{ $message }}</div>@enderror</div><div class="field" style="grid-column:1/-1"><span class="label">Vencimentos das parcelas *</span><p class="muted" style="margin:0">Confira e ajuste cada vencimento conforme o prazo combinado. As datas são sugeridas mês a mês.</p><div id="purchase-due-dates" class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px"></div>@error('due_dates')<div class="error">{{ $message }}</div>@enderror</div><div class="field"><label for="installment_count">Número de parcelas *</label><input id="installment_count" name="installment_count" type="number" min="1" max="36" required value="{{ old('installment_count', 1) }}">@error('installment_count')<div class="error">{{ $message }}</div>@enderror</div></div>
        <h2>Itens</h2>
        @error('items')<div class="alert alert-danger">{{ $message }}</div>@enderror
        <div id="purchase-items" style="display:grid;gap:10px">
            @foreach (old('items', [['product_id'=>'','quantity'=>'1.000','unit_cost'=>'0.00']]) as $index => $item)
                <div class="purchase-item" style="display:grid;grid-template-columns:minmax(220px,2fr) minmax(110px,1fr) minmax(130px,1fr) auto;gap:10px;align-items:end;border:1px solid #e1e8f0;border-radius:10px;padding:12px">
                    <div class="field" style="margin:0"><label>Produto *</label><select name="items[{{ $index }}][product_id]" required style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:8px;padding:0 8px;font:inherit"><option value="">Selecione</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected((string)($item['product_id'] ?? '') === (string)$product->id)>{{ $product->name }} (saldo {{ $product->stock_quantity }} {{ $product->unit }})</option>@endforeach</select></div>
                    <div class="field" style="margin:0"><label>Quantidade *</label><input name="items[{{ $index }}][quantity]" type="number" min="0.001" max="999999999.999" step="0.001" required value="{{ $item['quantity'] ?? '1.000' }}" style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:8px;padding:0 8px;font:inherit"></div>
                    <div class="field" style="margin:0"><label>Custo unitário (R$) *</label><input name="items[{{ $index }}][unit_cost]" type="number" min="0" max="42949672.95" step="0.01" required value="{{ $item['unit_cost'] ?? '0.00' }}" style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:8px;padding:0 8px;font:inherit"></div>
                    <button class="btn secondary remove-item" type="button" aria-label="Remover item">Remover</button>
                </div>
            @endforeach
        </div>
        @foreach ($errors->getMessages() as $key => $messages) @if (str_starts_with($key, 'items.'))<div class="error">{{ $messages[0] }}</div>@endif @endforeach
        <button class="btn secondary" type="button" id="add-item" style="margin:12px 0 20px">Adicionar item</button>
        <div class="field"><label for="notes">Observações</label><textarea id="notes" name="notes" rows="3" maxlength="5000" style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:10px;font:inherit">{{ old('notes') }}</textarea>@error('notes')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="actions"><button class="btn" type="submit" @disabled($suppliers->isEmpty() || $products->isEmpty())>Registrar compra</button><a class="btn secondary" href="{{ route('purchases.index') }}">Voltar</a></div>
    </form>
    <template id="item-template"><div class="purchase-item" style="display:grid;grid-template-columns:minmax(220px,2fr) minmax(110px,1fr) minmax(130px,1fr) auto;gap:10px;align-items:end;border:1px solid #e1e8f0;border-radius:10px;padding:12px"><div class="field" style="margin:0"><label>Produto *</label><select name="items[__INDEX__][product_id]" required style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:8px;padding:0 8px;font:inherit"><option value="">Selecione</option>@foreach ($products as $product)<option value="{{ $product->id }}">{{ $product->name }} (saldo {{ $product->stock_quantity }} {{ $product->unit }})</option>@endforeach</select></div><div class="field" style="margin:0"><label>Quantidade *</label><input name="items[__INDEX__][quantity]" type="number" min="0.001" max="999999999.999" step="0.001" required value="1.000" style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:8px;padding:0 8px;font:inherit"></div><div class="field" style="margin:0"><label>Custo unitário (R$) *</label><input name="items[__INDEX__][unit_cost]" type="number" min="0" max="42949672.95" step="0.01" required value="0.00" style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:8px;padding:0 8px;font:inherit"></div><button class="btn secondary remove-item" type="button">Remover</button></div></template>
</section>
<script>
(() => {
  const list = document.getElementById('purchase-items');
  const template = document.getElementById('item-template');
  let nextIndex = {{ count(old('items', [['product_id' => '', 'quantity' => '1.000', 'unit_cost' => '0.00']])) }};
  document.getElementById('add-item').addEventListener('click', () => {
    const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
    list.insertAdjacentHTML('beforeend', html);
  });
  list.addEventListener('click', (event) => {
    const button = event.target.closest('.remove-item');
    if (button && list.querySelectorAll('.purchase-item').length > 1) button.closest('.purchase-item').remove();
  });
  const installmentCount = document.getElementById('installment_count');
  const dueDates = document.getElementById('purchase-due-dates');
  const oldDueDates = @json(old('due_dates', []));
  let firstDueDate = oldDueDates[0] || @json(old('first_due_date', now()->toDateString()));
  const datePlusMonths = (value, months) => {
    const [year, month, day] = value.split('-').map(Number);
    const target = new Date(year, month - 1 + months, 1);
    const lastDay = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate();
    target.setDate(Math.min(day, lastDay));
    return [target.getFullYear(), String(target.getMonth() + 1).padStart(2, '0'), String(target.getDate()).padStart(2, '0')].join('-');
  };
  const renderDueDates = () => {
    const inputs = [];
    dueDates.replaceChildren();
    for (let i = 0; i < Number(installmentCount.value); i++) {
      const field = document.createElement('div');
      field.className = 'field';
      const label = document.createElement('label');
      label.htmlFor = `purchase_due_date_${i}`;
      label.textContent = `Parcela ${i + 1} — vencimento *`;
      const input = document.createElement('input');
      input.type = 'date';
      input.id = label.htmlFor;
      input.name = `due_dates[${i}]`;
      input.required = true;
      input.value = oldDueDates[i] || datePlusMonths(firstDueDate, i);
      input.addEventListener('change', () => {
        if (i === 0) {
          firstDueDate = input.value;
          inputs.forEach((other, index) => {
            if (index > 0 && !other.dataset.edited) other.value = datePlusMonths(firstDueDate, index);
          });
        }
        input.dataset.edited = 'true';
      });
      inputs.push(input);
      field.append(label, input);
      dueDates.append(field);
    }
  };
  installmentCount.addEventListener('input', renderDueDates);
  renderDueDates();

})();
</script>
@endsection
