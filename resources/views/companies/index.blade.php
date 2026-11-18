@extends('layouts.app', ['title' => 'Selecionar empresa · RMC Flow Comércio Cloud'])
@section('content')
<section class="card" style="max-width:760px;margin:20px auto">
    <h1 class="title">Selecione a empresa</h1>
    <p class="muted">Seu usuário tem acesso às empresas abaixo. Os dados de cada uma permanecem separados.</p>
    @forelse($memberships as $membership)
        <div class="company"><div><h3>{{ $membership->company->trade_name }}</h3><span class="muted">{{ $membership->role }} · {{ $membership->company->legal_name }}</span></div><form method="post" action="{{ route('companies.activate', $membership->company_id) }}">@csrf<button class="btn" type="submit">Abrir empresa</button></form></div>
    @empty
        <div class="alert alert-danger">Sua conta não possui uma empresa ativa vinculada. Fale com o administrador do sistema.</div>
    @endforelse
    <form method="post" action="{{ route('logout') }}" style="margin-top:20px">@csrf<button class="btn secondary" type="submit">Sair</button></form>
</section>
@endsection
