@extends('layouts.app', ['title' => 'Planos | RMC Flow Comércio Cloud'])
@section('content')
<section class="card narrow" style="max-width:760px">
    <p class="muted" style="margin-bottom:8px">RMC FLOW COMÉRCIO CLOUD</p>
    <h1 class="title">Escolha o plano ideal para sua empresa</h1>
    <p class="muted">Pague com segurança pelo Mercado Pago. Após a confirmação, você cadastra a empresa e cria o acesso do administrador.</p>
    @if($errors->has('checkout'))<div class="alert">{{ $errors->first('checkout') }}</div>@endif
    <form method="post" action="{{ route('checkout.start') }}">
        @csrf
        <div class="field">
            <label for="email">E-mail para receber o acesso</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="190" autocomplete="email" required>
            @error('email')<div class="error">{{ $message }}</div>@enderror
        </div>
        @foreach($plans as $plan)
            <div class="company" style="display:block;margin:14px 0">
                <h2 style="margin:0 0 8px">{{ $plan->name }}</h2>
                <p class="muted" style="margin-bottom:12px">Até {{ $plan->limits['users'] ?? 'ilimitados' }} usuários · Computadores ilimitados.</p>
                <p style="margin:0 0 14px">Primeira compra anual: <strong>R$ {{ number_format($plan->initial_price_cents / 100, 2, ',', '.') }}</strong><br>Renovação anual: <strong>R$ {{ number_format($plan->renewal_price_cents / 100, 2, ',', '.') }}</strong></p>
                <button class="btn" type="submit" name="plan_code" value="{{ $plan->code }}">Escolher {{ str_contains($plan->name, 'Equipe') ? 'Equipe' : 'Essencial' }} · R$ {{ number_format($plan->initial_price_cents / 100, 2, ',', '.') }}</button>
            </div>
        @endforeach
    </form>
    <p class="muted" style="margin:20px 0 0">Já possui uma conta? <a href="{{ route('login') }}">Entrar</a></p>
</section>
@endsection
