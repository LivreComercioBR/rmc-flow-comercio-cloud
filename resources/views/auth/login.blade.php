@extends('layouts.app', ['title' => 'Entrar · RMC Flow Comércio Cloud'])
@section('content')
<section class="card narrow">
    <h1 class="title">Acesse sua conta</h1>
    <p class="muted">Entre para continuar no RMC Flow Comércio Cloud.</p>
    @if($errors->any())<div class="alert alert-danger">Confira o e-mail e a senha e tente novamente.</div>@endif
    <form method="post" action="{{ route('login.store') }}">
        @csrf
        <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>@error('email')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="password">Senha</label><input id="password" name="password" type="password" autocomplete="current-password" required>@error('password')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label><input type="checkbox" name="remember" value="1"> Manter conectado</label></div>
        <button class="btn wide" type="submit">Entrar</button>
    </form>
    <p class="muted" style="margin:14px 0 0"><a href="{{ route('password.request') }}">Esqueci minha senha</a></p>
    <p class="muted" style="margin:20px 0 0">Ainda não contratou? <a href="{{ route('checkout.plans') }}">Conheça os planos</a></p>
</section>
@endsection
