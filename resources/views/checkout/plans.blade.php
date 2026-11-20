@extends('layouts.app', ['title' => 'Contratação | RMC Flow Comércio'])
@section('content')
<section class="card narrow" style="max-width:760px">
    <p class="muted" style="margin-bottom:8px">RMC FLOW COMÉRCIO</p>
    <h1 class="title">Escolha a edição ideal para sua empresa</h1>
    <p class="muted">Pague pelo Mercado Pago. Com um plano Cloud, você cadastra sua empresa e cria o acesso online após a confirmação. A edição Local é instalada e ativada em um computador Windows.</p>
    @if($errors->has('checkout'))<div class="alert alert-danger">{{ $errors->first('checkout') }}</div>@endif
    <form method="post" action="{{ route('checkout.start') }}">
        @csrf
        <div class="field">
            <label for="email">E-mail da compra</label>
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
        <div class="company" style="display:block;margin:14px 0">
            <h2 style="margin:0 0 8px">RMC Flow Comércio Local</h2>
            <p class="muted" style="margin-bottom:12px">Licença permanente para 1 computador Windows. A RMC Sites orienta a instalação e a ativação por código.</p>
            <p style="margin:0 0 14px">Pagamento único: <strong>R$ {{ number_format(\App\Models\LocalLicenseOrder::PRICE_CENTS / 100, 2, ',', '.') }}</strong></p>
            @if(config('services.mercado_pago.checkout_enabled'))
                <button class="btn" type="submit" formaction="{{ route('local-license.start') }}">Escolher Local · R$ {{ number_format(\App\Models\LocalLicenseOrder::PRICE_CENTS / 100, 2, ',', '.') }}</button>
            @else
                <p class="muted" style="margin:0">Pagamento online temporariamente indisponível.</p>
            @endif
        </div>
    </form>
    <p class="muted" style="margin:20px 0 0">Já possui uma conta? <a href="{{ route('login') }}">Entrar</a></p>
</section>
@endsection
