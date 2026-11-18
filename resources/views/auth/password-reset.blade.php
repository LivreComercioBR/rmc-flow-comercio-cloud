@extends('layouts.app', ['title' => 'Definir nova senha | RMC Flow Comércio Cloud'])
@section('content')
<section class="card narrow">
    <h1 class="title">Definir nova senha</h1>
    <p class="muted">Escolha uma senha com pelo menos 10 caracteres.</p>
    @if($errors->any())<div class="alert alert-danger">O link expirou ou os dados não são válidos. Confira e tente novamente.</div>@endif
    <form method="post" action="{{ route('password.update') }}">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required>@error('email')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="password">Nova senha</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required>@error('password')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="password_confirmation">Confirme a nova senha</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="10" required></div>
        <button class="btn wide" type="submit">Redefinir senha</button>
    </form>
</section>
@endsection
