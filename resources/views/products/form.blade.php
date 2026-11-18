@php($editing = isset($product))
<form method="post" action="{{ $editing ? route('products.update', $product->id) : route('products.store') }}">
    @csrf @if ($editing) @method('PUT') @endif
    <div class="field"><label for="name">Produto / peça *</label><input id="name" name="name" maxlength="180" required value="{{ old('name', $product->name ?? '') }}">@error('name')<div class="error">{{ $message }}</div>@enderror</div>
    <div class="grid"><div class="field"><label for="category_id">Categoria</label><select id="category_id" name="category_id" style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit"><option value="">Sem categoria</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string)old('category_id', $product->category_id ?? '') === (string)$category->id)>{{ $category->name }}</option>@endforeach</select>@error('category_id')<div class="error">{{ $message }}</div>@enderror</div><div class="field"><label for="unit">Unidade *</label><input id="unit" name="unit" maxlength="12" required value="{{ old('unit', $product->unit ?? 'un') }}">@error('unit')<div class="error">{{ $message }}</div>@enderror</div></div>
    <div class="grid"><div class="field"><label for="sku">Código / SKU</label><input id="sku" name="sku" maxlength="64" value="{{ old('sku', $product->sku ?? '') }}">@error('sku')<div class="error">{{ $message }}</div>@enderror</div><div class="field"><label for="barcode">Código de barras</label><input id="barcode" name="barcode" maxlength="64" value="{{ old('barcode', $product->barcode ?? '') }}">@error('barcode')<div class="error">{{ $message }}</div>@enderror</div></div>
    <div class="grid"><div class="field"><label for="cost_price">Preço de custo (R$)</label><input id="cost_price" name="cost_price" type="number" min="0" max="42949672.95" step="0.01" value="{{ old('cost_price', isset($product) ? number_format($product->cost_price_cents / 100, 2, '.', '') : '0.00') }}">@error('cost_price')<div class="error">{{ $message }}</div>@enderror</div><div class="field"><label for="sale_price">Preço de venda (R$)</label><input id="sale_price" name="sale_price" type="number" min="0" max="42949672.95" step="0.01" value="{{ old('sale_price', isset($product) ? number_format($product->sale_price_cents / 100, 2, '.', '') : '0.00') }}">@error('sale_price')<div class="error">{{ $message }}</div>@enderror</div></div>
    <fieldset style="border:1px solid #dbe3ed;border-radius:10px;padding:16px;margin:18px 0"><legend style="font-weight:700;padding:0 8px">Custos, impostos e formação de preço</legend><p class="muted" style="margin-top:0">Os percentuais são calculados sobre o custo base; o lucro é um acréscimo sobre esse custo.</p>
        <div class="grid">@foreach ([['icms_rate','ICMS (%)'],['ipi_rate','IPI (%)'],['import_tax_rate','II – importação (%)'],['export_tax_rate','IE – exportação (%)'],['iss_rate','ISS – serviços (%)'],['operational_cost_rate','Custo operacional (%)'],['profit_rate','Lucro sobre custo (%)']] as [$field,$label])<div class="field"><label for="{{ $field }}">{{ $label }}</label><input class="price-rate" id="{{ $field }}" name="{{ $field }}" type="number" min="0" max="999999.9999" step="0.01" value="{{ old($field, $product->$field ?? '0') }}">@error($field)<div class="error">{{ $message }}</div>@enderror</div>@endforeach
        <div class="field"><label for="other_costs">Outros custos (R$)</label><input id="other_costs" name="other_costs" type="number" min="0" max="42949672.95" step="0.01" value="{{ old('other_costs', isset($product) ? number_format($product->other_costs_cents / 100, 2, '.', '') : '0.00') }}">@error('other_costs')<div class="error">{{ $message }}</div>@enderror</div></div>
        <p style="margin:12px 0"><strong>Preço sugerido: R$ <span id="suggested_price">0,00</span></strong></p><button class="btn secondary" id="use_suggested_price" type="button">Usar preço sugerido</button>
    </fieldset>
    <div class="grid"><div class="field"><label for="minimum_stock">Estoque mínimo</label><input id="minimum_stock" name="minimum_stock" type="number" min="0" max="999999999" step="1" required value="{{ old('minimum_stock', isset($product) ? (int) $product->minimum_stock : '0') }}">@error('minimum_stock')<div class="error">{{ $message }}</div>@enderror</div>@unless($editing)<div class="field"><label for="initial_stock">Saldo inicial</label><input id="initial_stock" name="initial_stock" type="number" min="0" max="999999999" step="1" value="{{ old('initial_stock', '0') }}"><small class="muted">O saldo será registrado como entrada inicial.</small>@error('initial_stock')<div class="error">{{ $message }}</div>@enderror</div>@endunless</div>
    <div class="field"><label for="shelf">Prateleira / localização</label><input id="shelf" name="shelf" maxlength="80" value="{{ old('shelf', $product->shelf ?? '') }}">@error('shelf')<div class="error">{{ $message }}</div>@enderror</div>
    <div class="actions"><button class="btn" type="submit">{{ $editing ? 'Salvar alterações' : 'Cadastrar produto' }}</button><a class="btn secondary" href="{{ route('products.index') }}">Voltar</a></div>
</form>
<script>
(() => {
    const base = document.getElementById('cost_price');
    const other = document.getElementById('other_costs');
    const rates = [...document.querySelectorAll('.price-rate')];
    const output = document.getElementById('suggested_price');
    const sale = document.getElementById('sale_price');
    const calculate = () => {
        const cost = Number(base.value || 0);
        const percent = rates.reduce((sum, input) => sum + Number(input.value || 0), 0);
        const suggested = cost + (cost * percent / 100) + Number(other.value || 0);
        output.textContent = suggested.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        return suggested;
    };
    [base, other, ...rates].forEach(input => input.addEventListener('input', calculate));
    document.getElementById('use_suggested_price').addEventListener('click', () => { sale.value = calculate().toFixed(2); });
    calculate();
})();
</script>
