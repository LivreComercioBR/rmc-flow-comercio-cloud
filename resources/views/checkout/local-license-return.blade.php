@extends('layouts.app', ['title' => 'Pedido | RMC Flow Comércio Local'])
@section('content')
<section class="card narrow">
    <p class="muted">RMC FLOW COMÉRCIO LOCAL</p>
    @if ($order->status === 'paid')
        <h1 class="title">Pagamento confirmado</h1>
        <p>Solicite o instalador à RMC Sites. Depois de instalar, gere o código de solicitação no computador em que vai usar o sistema e envie o código ao suporte para receber a ativação.</p>
    @elseif ($order->status === 'review')
        <h1 class="title">Pedido em análise</h1>
        <p>Fale com o suporte e informe a referência abaixo.</p>
    @else
        <h1 class="title">Pedido aguardando confirmação</h1>
        <p>Se você já pagou, aguarde a confirmação do Mercado Pago e atualize esta página antes de tentar pagar novamente. Se o pagamento foi recusado, fale com o suporte.</p>
    @endif
    <p>Referência do pedido: <strong>{{ $order->reference }}</strong></p>
    <a class="btn" href="{{ $supportUrl }}" rel="noreferrer">Falar com o suporte</a>
</section>
@endsection

