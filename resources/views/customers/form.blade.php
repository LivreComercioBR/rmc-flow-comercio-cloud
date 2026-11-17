@php($editing = isset($customer))
<form method="post" action="{{ $editing ? route('customers.update', $customer->id) : route('customers.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif @if (! $editing && $returnTo)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
    <div class="field"><label for="name">Nome / razão social *</label><input id="name" name="name" maxlength="160" required value="{{ old('name', $customer->name ?? '') }}">@error('name')<div class="error">{{ $message }}</div>@enderror</div>
    <div class="grid">
        <div class="field"><label for="document">CPF / CNPJ</label><input id="document" name="document" maxlength="32" value="{{ old('document', $customer->document ?? '') }}">@error('document')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="phone">Telefone / WhatsApp</label><input id="phone" name="phone" maxlength="32" value="{{ old('phone', $customer->phone ?? '') }}">@error('phone')<div class="error">{{ $message }}</div>@enderror</div>
    </div>
    <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" maxlength="190" value="{{ old('email', $customer->email ?? '') }}">@error('email')<div class="error">{{ $message }}</div>@enderror</div>
    <div class="field"><label for="address">Endereço</label><input id="address" name="address" maxlength="255" value="{{ old('address', $customer->address ?? '') }}">@error('address')<div class="error">{{ $message }}</div>@enderror</div>
    <div class="field"><label for="notes">Observações</label><textarea id="notes" name="notes" rows="4" maxlength="5000" style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:10px;font:inherit">{{ old('notes', $customer->notes ?? '') }}</textarea>@error('notes')<div class="error">{{ $message }}</div>@enderror</div>
    <div class="actions"><button class="btn" type="submit">{{ $editing ? 'Salvar alterações' : 'Cadastrar cliente' }}</button><a class="btn secondary" href="{{ $returnTo ? route($returnTo) : route('customers.index') }}">Voltar</a></div>
</form>
