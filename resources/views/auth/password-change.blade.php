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
</section>
@endsection
