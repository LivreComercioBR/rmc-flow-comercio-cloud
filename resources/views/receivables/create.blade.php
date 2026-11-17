@extends('layouts.app', ['title' => 'Nova receita | RMC Flow Comércio Cloud'])
@section('content')
<section class="card"><div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap"><div><p class="muted">FINANCEIRO</p><h1 class="title">Lançar receita</h1><p class="muted">Crie uma conta a receber manual sem associar uma venda de PDV.</p></div><a class="btn secondary" href="{{ route('receivables.index') }}">Voltar</a></div>
    @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('receivables.manual.store') }}" class="grid" style="max-width:900px;margin-top:20px">@csrf<input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">
        <div class="field" style="grid-column:1/-1"><label for="description">Descrição</label><input id="description" name="description" maxlength="180" required value="{{ old('description') }}"></div>
        <div class="field"><label for="customer_id">Cliente/devedor (opcional)</label><select id="customer_id" name="customer_id" style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit"><option value="">Não informado</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}" @selected((string)old('customer_id', $customerId) === (string)$customer->id)>{{ $customer->name }}</option>@endforeach</select><a href="{{ route('customers.create', ['return_to' => 'receivables.manual.create']) }}">Cadastrar cliente/devedor</a></div>
        <div class="field"><label for="financial_category_id">Categoria (opcional)</label><select id="financial_category_id" name="financial_category_id" style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit"><option value="">Sem categoria</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string)old('financial_category_id') === (string)$category->id)>{{ $category->name }}</option>@endforeach</select></div>
        <div class="field"><label for="amount">Valor total (R$)</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" required value="{{ old('amount') }}"></div>
        <div class="field"><label for="installments">Parcelas</label><select id="installments" name="installments" style="width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit">@for($i=1;$i<=24;$i++)<option value="{{ $i }}" @selected((int)old('installments', 1) === $i)>{{ $i }}x</option>@endfor</select></div>
        <div class="field"><label for="first_due_date">Vencimento da primeira parcela</label><input id="first_due_date" name="first_due_date" type="date" required value="{{ old('first_due_date', now()->toDateString()) }}"></div>
        <div style="align-self:end"><button class="btn">Salvar receita</button></div>
    </form>
</section>
@endsection
