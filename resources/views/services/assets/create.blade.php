@extends('layouts.app', ['title' => 'Cadastrar veículo ou equipamento | RMC Flow Comércio Cloud'])

@section('content')
<section class="card">
    <p class="muted">MÓDULO DE SERVIÇOS</p>
    <h1 class="title">Cadastrar veículo / equipamento</h1>
    <p class="muted">Vincule o item a um cliente para consultar os serviços realizados nele.</p>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($customers->isEmpty())
        <div class="alert alert-warming">Cadastre um cliente antes de cadastrar um veículo ou equipamento. <a href="{{ route('customers.create') }}">Cadastrar cliente</a></div>
    @else
        <form method="post" action="{{ route('services.assets.storeStandalone') }}" class="grid" style="max-width:950px">
            @csrf
            <div class="field">
                <label for="customer_id">Cliente *</label>
                <select id="customer_id" name="customer_id" required>
                    <option value="">Selecione o cliente</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="asset_type">Tipo *</label>
                <select id="asset_type" name="asset_type" required>
                    <option value="vehicle" @selected(old('asset_type') === 'vehicle')>Veículo</option>
                    <option value="motorcycle" @selected(old('asset_type') === 'motorcycle')>Motocicleta</option>
                    <option value="equipment" @selected(old('asset_type', 'equipment') === 'equipment')>Equipamento</option>
                    <option value="other" @selected(old('asset_type') === 'other')>Outro</option>
                </select>
            </div>
            <div class="field">
                <label for="name">Nome / descrição *</label>
                <input id="name" name="name" value="{{ old('name') }}" maxlength="180" required placeholder="Ex.: Honda Civic, iPhone 13, notebook Dell">
            </div>
            <div class="field">
                <label for="identifier">Placa, série ou identificação</label>
                <input id="identifier" name="identifier" value="{{ old('identifier') }}" maxlength="80">
            </div>
            <div class="field">
                <label for="manufacturer">Fabricante</label>
                <input id="manufacturer" name="manufacturer" value="{{ old('manufacturer') }}" maxlength="120">
            </div>
            <div class="field">
                <label for="model">Modelo</label>
                <input id="model" name="model" value="{{ old('model') }}" maxlength="120">
            </div>
            <div class="field">
                <label for="year">Ano</label>
                <input id="year" name="year" type="number" min="1900" max="{{ now()->year + 2 }}" value="{{ old('year') }}">
            </div>
            <div class="field">
                <label for="color">Cor</label>
                <input id="color" name="color" value="{{ old('color') }}" maxlength="60">
            </div>
            <div class="field" style="grid-column:1/-1">
                <label for="notes">Observações</label>
                <textarea id="notes" name="notes" maxlength="2000" rows="3">{{ old('notes') }}</textarea>
            </div>
            <div class="actions" style="grid-column:1/-1">
                <button class="btn" type="submit">Salvar cadastro</button>
                <a class="btn secondary" href="{{ route('services.assets.index') }}">Voltar</a>
            </div>
        </form>
    @endif
</section>
@endsection
