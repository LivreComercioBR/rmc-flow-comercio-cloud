@extends('layouts.app', ['title' => 'Minha senha | RMC Flow Comércio Cloud'])
@section('content')
<section class="card narrow">
    <p class="muted" style="margin-bottom:8px">MINHA CONTA</p>
    <h1 class="title">Alterar senha</h1>
    <p class="muted">Informe a senha atual e escolha uma nova senha com pelo menos 10 caracteres.</p>
    @if(session('password_notice'))<div class="alert alert-success" role="status">{{ session('password_notice') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('account.password.update') }}">
        @csrf @method('PUT')
        <div class="field"><label for="current_password">Senha atual</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
        <div class="field"><label for="password">Nova senha</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required></div>
        <div class="field"><label for="password_confirmation">Confirme a nova senha</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="10" required></div>
        <button class="btn" type="submit">Salvar nova senha</button>
    </form>

    <hr style="border:0;border-top:1px solid #e1e8f0;margin:28px 0">
    <h2 style="font-size:20px;color:#102c4d;margin:0 0 8px">Alterar e-mail de acesso</h2>
    <p class="muted">E-mail atual: <strong>{{ auth()->user()->email }}</strong>. Informe o novo endereço e sua senha atual. Não enviaremos confirmação ao endereço antigo; o novo endereço deverá ser confirmado.</p>
    @if(session('email_notice'))<div class="alert alert-success" role="status">{{ session('email_notice') }}</div>@endif
    <form method="post" action="{{ route('account.email.update') }}">
        @csrf @method('PATCH')
        <div class="field"><label for="email">Novo e-mail</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="190" required></div>
        <div class="field"><label for="email_current_password">Senha atual</label><input id="email_current_password" name="current_password" type="password" autocomplete="current-password" required></div>
        <button class="btn secondary" type="submit">Salvar novo e-mail</button>
    </form>
    </section>
@endsection
