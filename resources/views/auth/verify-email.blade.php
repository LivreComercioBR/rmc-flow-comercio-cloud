@extends('layouts.app', ['title' => 'Confirme seu e-mail | RMC Flow Comércio Cloud'])
@section('content')
<section class="card narrow">
    <h1 class="title">Confirme seu e-mail</h1>
    <p class="muted">Confirme <strong>{{ auth()->user()->email }}</strong> para liberar as funções do sistema. O link expira em 60 minutos. Se não recebeu a mensagem, solicite outro link abaixo.</p>
    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('verification.send') }}">@csrf<button class="btn wide" type="submit">Enviar outro link</button></form>
    <details style="margin-top:18px" @if($errors->has('new_email') || $errors->has('current_password')) open @endif>
        <summary style="cursor:pointer;color:#173b8f;font-weight:600">O e-mail est&aacute; incorreto? Alterar endere&ccedil;o</summary>
        <p class="muted">Informe o novo e-mail e sua senha atual. O novo endere&ccedil;o tamb&eacute;m ser&aacute; usado para acessar sua conta.</p>
        <form method="post" action="{{ route('verification.email.update') }}">@csrf
            <div class="field">
                <label for="new_email">Novo e-mail</label>
                <input id="new_email" name="new_email" type="email" value="{{ old('new_email') }}" autocomplete="email" maxlength="190" required>
                @error('new_email')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="current_password">Senha atual</label>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                @error('current_password')<div class="error">{{ $message }}</div>@enderror
            </div>
            <button class="btn secondary wide" type="submit">Atualizar e enviar novo link</button>
        </form>
    </details>
    <form method="post" action="{{ route('logout') }}" style="margin-top:12px">@csrf<button class="btn secondary wide" type="submit">Sair</button></form>
</section>
@endsection
