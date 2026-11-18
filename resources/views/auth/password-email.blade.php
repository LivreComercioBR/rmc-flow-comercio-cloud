@extends('layouts.app', ['title' => 'Recuperar senha | RMC Flow Comércio Cloud'])
@section('content')
<section class="card narrow">
    <h1 class="title">Recuperar senha</h1>
    <p class="muted">Informe o e-mail da sua conta para receber um link seguro.</p>
    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @error('email')<div class="alert alert-danger">{{ $message }}</div>@enderror
    <form method="post" action="{{ route('password.email') }}">@csrf
        <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus></div>
        <button class="btn wide" type="submit">Enviar link de redefinição</button>
    </form>
    <p class="muted" style="margin:20px 0 0"><a href="{{ route('login') }}">Voltar ao login</a></p>
</section>
@endsection
