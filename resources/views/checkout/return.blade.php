@extends('layouts.app', ['title' => 'Status da compra | RMC Flow Comércio Cloud'])
@section('content')
<section class="card narrow">
    <h1 class="title">Status da sua compra</h1>
    <p class="muted">{{ $intent->plan->name }} · R$ {{ number_format($intent->expected_amount_cents / 100, 2, ',', '.') }}</p>
    @if($intent->status === 'approved')
        <div class="alert alert-success">Pagamento confirmado. Continue para cadastrar a empresa e criar o acesso do administrador.</div>
        <a class="btn wide" href="{{ route('checkout.register', $intent->token) }}">Continuar para o cadastro</a>
    @elseif($intent->status === 'awaiting_activation_policy')
        <div class="alert alert-warming">O pagamento foi confirmado, mas a liberação do cadastro aguarda uma configuração do sistema. Não faça outro pagamento.</div>
    @elseif($intent->status === 'not_approved')
        <div class="alert alert-danger">O Mercado Pago não aprovou este pagamento. Nenhuma empresa ou assinatura foi criada. Você pode voltar aos planos e iniciar outra tentativa.</div>
        <a class="btn secondary" href="{{ route('checkout.plans') }}">Voltar aos planos</a>
    @elseif($intent->status === 'review_required')
        <div class="alert alert-danger">Não foi possível validar os dados deste pagamento automaticamente. A assinatura não foi ativada. Entre em contato com o suporte antes de tentar novamente.</div>
    @else
        <div class="alert alert-warming">Ainda aguardamos a confirmação do Mercado Pago. Esta página não confirma o pagamento por si só. Aguarde alguns instantes e atualize.</div>
        <a class="btn secondary" href="{{ route('checkout.return', $intent->token) }}">Atualizar status</a>
    @endif
</section>
@endsection
