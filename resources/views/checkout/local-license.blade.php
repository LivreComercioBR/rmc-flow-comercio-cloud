@extends('layouts.app', ['title' => 'Comprar | RMC Flow Comércio Local'])
@section('content')
<section class="card narrow">
    <p class="muted">RMC FLOW COMÉRCIO LOCAL</p>
    <h1 class="title">Licença para Windows</h1>
    <p><strong>R$ 99,00</strong> em pagamento único pelo Mercado Pago. Licença permanente para um computador.</p>
    <p>Após a confirmação, fale com a RMC Sites para receber o instalador. Gere o código de solicitação no computador de uso e envie ao suporte para receber o código de ativação.</p>
    @if ($enabled)
        <form method="post" action="{{ route('local-license.start') }}">
            @csrf
            <div class="field">
                <label for="email">E-mail usado na compra</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="190" autocomplete="email" required>
                @error('email')<p class="error">{{ $message }}</p>@enderror
            </div>
            @error('checkout')<p class="error">{{ $message }}</p>@enderror
            <button class="btn" type="submit">Pagar R$ 99,00 no Mercado Pago</button>
        </form>
    @else
        <p>O pagamento online está temporariamente indisponível.</p>
        <a class="btn" href="https://wa.me/5519990136498">Falar com a RMC Sites</a>
    @endif
</section>
@endsection

