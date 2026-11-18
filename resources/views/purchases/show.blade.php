@extends('layouts.app', ['title' => 'Detalhes da compra | RMC Flow Comércio Cloud'])

@section('content')
<section class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
        <div>
            <p class="muted" style="margin-bottom:8px">COMPRA #{{ $purchase->id }}</p>
            <h1 class="title">{{ $purchase->supplier->name }}</h1>
            <p class="muted">
                {{ $purchase->purchased_at->format('d/m/Y') }}
                · Documento {{ $purchase->invoice_number ?: 'não informado' }}
                · Status: {{ $purchase->status === 'completed' ? 'Concluída' : 'Cancelada' }}
            </p>
            <p class="muted">
                Forma acordada:
                {{ [
                    'supplier_terms' => 'Prazo / acordo com fornecedor',
                    'boleto' => 'Boleto',
                    'pix' => 'PIX',
                    'bank_transfer' => 'Transferência bancária',
                    'cash' => 'Dinheiro',
                    'debit_card' => 'Cartão de débito',
                    'credit_card' => 'Cartão de crédito',
                    'other' => 'Outro',
                ][$purchase->payment_method ?? 'supplier_terms'] ?? 'Prazo / acordo com fornecedor' }}
            </p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('purchases.index') }}">Compras</a>
            <a class="btn secondary" href="{{ route('payables.index') }}">Contas a pagar</a>
        </div>
    </div>

    @if (session('purchase_notice'))
        <div class="alert alert-{{ session('purchase_notice_type', 'success') }}" role="status">{{ session('purchase_notice') }}</div>
    @endif

    @if ($errors->has('cancellation'))
        <div class="alert alert-danger" role="alert">{{ $errors->first('cancellation') }}</div>
    @endif

    <h2>Itens da compra</h2>
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;text-align:left">
            <thead>
                <tr>
                    <th style="padding:10px;border-bottom:1px solid #dbe3ed">Produto</th>
                    <th style="padding:10px;border-bottom:1px solid #dbe3ed">Quantidade</th>
                    <th style="padding:10px;border-bottom:1px solid #dbe3ed">Custo unitário</th>
                    <th style="padding:10px;border-bottom:1px solid #dbe3ed">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($purchase->items as $item)
                    <tr>
                        <td style="padding:10px;border-bottom:1px solid #edf1f5">{{ $item->product?->name ?? 'Produto indisponível' }}</td>
                        <td style="padding:10px;border-bottom:1px solid #edf1f5">{{ $item->quantity }} {{ $item->product?->unit }}</td>
                        <td style="padding:10px;border-bottom:1px solid #edf1f5">R$ {{ number_format($item->unit_cost_cents / 100, 2, ',', '.') }}</td>
                        <td style="padding:10px;border-bottom:1px solid #edf1f5">R$ {{ number_format($item->total_cents / 100, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" style="padding:10px;text-align:right">Total da compra</th>
                    <th style="padding:10px">R$ {{ number_format($purchase->total_cents / 100, 2, ',', '.') }}</th>
                </tr>
            </tfoot>
        </table>
    </div>

    @if ($purchase->notes)
        <p><strong>Observações:</strong> {{ $purchase->notes }}</p>
    @endif

    <h2>Parcelas a pagar</h2>
    @if ($purchase->payables->isEmpty())
        <div class="alert alert-warming">Nenhuma parcela registrada.</div>
    @else
        <ul>
            @foreach ($purchase->payables as $payable)
                <li>
                    <strong>{{ $payable->installment_number }}ª parcela</strong>
                    · vence {{ $payable->due_date->format('d/m/Y') }}
                    · total R$ {{ number_format($payable->amount_cents / 100, 2, ',', '.') }}
                    · pago R$ {{ number_format($payable->paid_cents / 100, 2, ',', '.') }}
                    · saldo R$ {{ number_format($payable->remainingCents() / 100, 2, ',', '.') }}
                    · {{ [
                        'pending' => 'Pendente',
                        'partial' => 'Parcial',
                        'paid' => 'Pago',
                        'cancelled' => 'Cancelado',
                        'cancelled_partial' => 'Saldo cancelado; pagamento preservado',
                    ][$payable->status] ?? $payable->status }}
                    @if ($payable->payments->isNotEmpty())
                        <ul>
                            @foreach ($payable->payments as $payment)
                                <li>
                                    {{ $payment->paid_at->format('d/m/Y H:i') }}
                                    · R$ {{ number_format($payment->amount_cents / 100, 2, ',', '.') }}
                                    · {{ [
                                        'cash' => 'Dinheiro',
                                        'pix' => 'PIX',
                                        'credit_card' => 'Cartão de crédito',
                                        'debit_card' => 'Cartão de débito',
                                        'bank_transfer' => 'Transferência',
                                        'other' => 'Outro',
                                    ][$payment->payment_method] ?? $payment->payment_method }}
                                    @if ($payment->reference)
                                        · Ref.: {{ $payment->reference }}
                                    @endif
                                    @if ($payment->creator)
                                        · registrado por {{ $payment->creator->name }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($purchase->status === 'cancelled')
        <div class="alert alert-warming">
            Cancelada em {{ $purchase->cancelled_at?->format('d/m/Y H:i') }}.
            Motivo: {{ $purchase->cancellation_reason }}
        </div>
    @endif

    @if ($purchase->status === 'completed')
        <hr style="border:0;border-top:1px solid #e1e8f0;margin:24px 0">
        <form method="post" action="{{ route('purchases.cancel', $purchase->id) }}" onsubmit="return confirm('Cancelar a compra? O estoque será estornado e as parcelas em aberto serão canceladas.')">
            @csrf
            @method('PATCH')
            <div class="field">
                <label for="cancellation_reason">Motivo para cancelar *</label>
                <input id="cancellation_reason" name="cancellation_reason" maxlength="180" required value="{{ old('cancellation_reason') }}">
                @error('cancellation_reason')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>
            <button class="btn secondary" type="submit">Cancelar compra e estornar estoque</button>
        </form>
    @endif
</section>
@endsection
