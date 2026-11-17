@extends('layouts.app', ['title' => 'Cadastro da empresa | RMC Flow Comércio Cloud'])
@section('content')
<section class="card narrow">
    <h1 class="title">Pagamento confirmado</h1>
    <p class="muted">Agora cadastre sua empresa e o administrador. Plano {{ $intent->plan->name }} · até {{ $intent->plan->limits['users'] ?? 'ilimitados' }} usuários.</p>
    <div class="alert alert-warning">A assinatura será vinculada à empresa após concluir este formulário.</div>
    <form method="post" action="{{ route('checkout.register.complete', $intent->token) }}">
        @csrf
        <div class="grid">
            <div class="field"><label for="legal_name">Razão social</label><input id="legal_name" name="legal_name" value="{{ old('legal_name') }}" maxlength="190" required>@error('legal_name')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="trade_name">Nome fantasia</label><input id="trade_name" name="trade_name" value="{{ old('trade_name') }}" maxlength="190" required>@error('trade_name')<div class="error">{{ $message }}</div>@enderror</div>
        </div>
        <div class="grid">
            <div class="field"><label for="document">CPF/CNPJ (opcional)</label><input id="document" name="document" value="{{ old('document') }}" maxlength="32">@error('document')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="activity">Atividade principal (opcional)</label><input id="activity" name="activity" value="{{ old('activity') }}" maxlength="120"></div>
        </div>
        <div class="field"><label for="name">Nome do administrador</label><input id="name" name="name" value="{{ old('name') }}" maxlength="190" autocomplete="name" required>@error('name')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label>E-mail de acesso</label><input type="email" value="{{ $intent->contact_email }}" readonly disabled><small class="muted" style="display:block;margin:5px 0 0">Este é o e-mail informado no início da compra.</small></div>
        <div class="grid">
            <div class="field"><label for="password">Senha (mínimo 10 caracteres)</label><input id="password" name="password" type="password" minlength="10" autocomplete="new-password" required>@error('password')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="password_confirmation">Confirme a senha</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="10" autocomplete="new-password" required></div>
        </div>
        <button class="btn wide" type="submit">Cadastrar empresa e ativar acesso</button>
    </form>
</section>
@endsection
