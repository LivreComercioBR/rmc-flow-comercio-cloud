@extends('layouts.app', ['title' => 'Acesso não autorizado | RMC Flow Comércio Cloud'])
@section('content')
<section class="card narrow" role="alert" aria-labelledby="access-denied-title">
    <p class="muted" style="margin-bottom:8px">ACESSO RESTRITO</p>
    <h1 class="title" id="access-denied-title">Você não tem acesso a esta página</h1>
    <p class="muted">Seu perfil não tem permissão para acessar esta área ou consultar estes dados. Se você acredita que deveria ter acesso, peça ao administrador da empresa para verificar suas permissões.</p>
    <div class="actions">
        @if(auth()->check())
            <a class="btn" href="{{ route('dashboard') }}">Voltar ao Painel</a>
        @else
            <a class="btn" href="{{ route('login') }}">Ir para o login</a>
        @endif
        <button class="btn secondary" type="button" onclick="history.back()">Voltar à página anterior</button>
    </div>
</section>
@endsection
