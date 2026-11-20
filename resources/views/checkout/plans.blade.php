<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101d52">
    <title>Contratação | RMC Flow Comércio</title>
    @include('checkout.plan-styles')
</head>
<body>
    <a class="skip-link" href="#contratacao">Ir para a contratação</a>
    <header class="site-header">
        <div class="page-width header-content">
            <a class="brand" href="https://rmcsites.com.br/">
                <img src="https://rmcsites.com.br/loja/assets/brand/rmc-sites.png" alt="" width="34" height="34">
                <span>RMC Sites</span>
            </a>
            <nav class="header-links" aria-label="Navegação principal">
                <a href="https://rmcsites.com.br/loja/">Voltar à loja</a>
                <a class="account-link" href="{{ auth()->check() ? route('companies.index') : route('login') }}">{{ auth()->check() ? 'Minhas empresas' : 'Entrar' }}</a>
            </nav>
        </div>
    </header>

    <main id="contratacao">
        <form method="post" action="{{ route('checkout.start') }}">
            @csrf
            <section class="hero" aria-labelledby="checkout-title">
                <div class="page-width hero-layout">
                    <div>
                        <p class="eyebrow"><span></span> RMC Flow Comércio</p>
                        <h1 id="checkout-title">Escolha a edição <em>para sua empresa.</em></h1>
                        <p class="hero-copy">Escolha o Cloud para acessar pelo navegador ou a edição Local para instalar em um computador Windows.</p>
                    </div>
                    <div class="email-panel">
                        <label for="email">E-mail da compra</label>
                        <p id="email-help">Informe seu e-mail para continuar ao Mercado Pago.</p>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="190" autocomplete="email" required
                            aria-describedby="email-help{{ $errors->has('email') ? ' email-error' : '' }}" @error('email') aria-invalid="true" @enderror>
                        @error('email')<p id="email-error" class="form-error" role="alert">{{ $message }}</p>@enderror
                        @error('checkout')<p class="form-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="page-width plans" aria-labelledby="plans-title">
                <div class="plans-heading">
                    <h2 id="plans-title">Planos e licença</h2>
                    <p>Pagamento pelo Mercado Pago</p>
                </div>

                <div class="plan-grid">
                    @foreach($plans as $plan)
                        @php
                            $isTeam = str_contains($plan->name, 'Equipe');
                            $planLabel = $isTeam ? 'Equipe' : 'Essencial';
                            $initialPrice = number_format($plan->initial_price_cents / 100, 2, ',', '.');
                            $renewalPrice = number_format($plan->renewal_price_cents / 100, 2, ',', '.');
                        @endphp
                        <article @class(['plan-card', 'plan-card-accent' => !$isTeam])>
                            <p class="plan-type">Cloud</p>
                            <h3>{{ $planLabel }}</h3>
                            <p class="plan-description">Acesso online pelo navegador.</p>
                            <p class="price"><strong>R$ {{ $initialPrice }}</strong> <span>no primeiro ano</span></p>
                            <p class="billing">Renovação anual de <strong>R$ {{ $renewalPrice }}</strong></p>
                            <ul class="features">
                                <li>{{ isset($plan->limits['users']) ? 'Até '.$plan->limits['users'].' usuários' : 'Usuários ilimitados' }}</li>
                                <li>Computadores ilimitados</li>
                            </ul>
                            <button @class(['choose', 'choose-accent' => !$isTeam]) type="submit" name="plan_code" value="{{ $plan->code }}">Escolher {{ $planLabel }} · R$ {{ $initialPrice }}</button>
                        </article>
                    @endforeach

                    @php($localPrice = number_format(\App\Models\LocalLicenseOrder::PRICE_CENTS / 100, 2, ',', '.'))
                    <article class="plan-card">
                        <p class="plan-type plan-type-local">Aplicativo Windows</p>
                        <h3>Comércio Local</h3>
                        <p class="plan-description">Instalado no computador da sua empresa.</p>
                        <p class="price"><strong>R$ {{ $localPrice }}</strong></p>
                        <p class="billing"><strong>Pagamento único</strong></p>
                        <ul class="features">
                            <li>Licença permanente para 1 computador</li>
                            <li>Instalação e ativação orientadas pela RMC Sites</li>
                        </ul>
                        @if(config('services.mercado_pago.checkout_enabled'))
                            <button class="choose" type="submit" formaction="{{ route('local-license.start') }}">Escolher Local · R$ {{ $localPrice }}</button>
                        @else
                            <p class="unavailable">Pagamento online temporariamente indisponível.</p>
                        @endif
                    </article>
                </div>
            </section>
        </form>
    </main>
    <footer class="site-footer"><div class="page-width footer-content"><span>RMC Sites</span><span>RMC Flow Comércio</span></div></footer>
</body>
</html>
