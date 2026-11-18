@extends('layouts.app', ['title' => 'Aceitar convite | RMC Flow Comércio Cloud'])
@section('content')
<section class="card narrow">
    <p class="muted" style="margin-bottom:8px">CONVITE DE ACESSO</p><h1 class="title">{{ $invitation->company->trade_name }}</h1>
    <p class="muted">Você foi convidado(a) com o perfil <strong>{{ $invitation->role }}</strong>. O convite expira em {{ $invitation->expires_at->format('d/m/Y H:i') }}.</p>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('company-invitations.accept', ['token' => request()->route('token')]) }}">@csrf
        <div class="field"><label>E-mail da conta</label><input value="{{ $invitation->email }}" readonly></div>
        @if(!$existingUser && $needsPassword)<div class="field"><label for="invite_name">Seu nome</label><input id="invite_name" name="name" maxlength="190" required value="{{ old('name') }}"></div>@endif
        @if($needsPassword)<div class="field"><label for="invite_password">{{ $existingUser ? 'Senha da sua conta' : 'Crie uma senha (mínimo de 10 caracteres)' }}</label><input id="invite_password" name="password" type="password" @if(!$existingUser)minlength="10" autocomplete="new-password"@else autocomplete="current-password"@endif required></div>@if(!$existingUser)<div class="field"><label for="invite_password_confirmation">Confirme a senha</label><input id="invite_password_confirmation" name="password_confirmation" type="password" minlength="10" autocomplete="new-password" required></div>@endif @else<p>Você está conectado como {{ auth()->user()->email }}. Ao aceitar, sua conta receberá acesso a esta empresa.</p>@endif
        <button class="btn wide" type="submit">Aceitar convite</button>
    </form>
</section>
@endsection
