<?php

namespace App\Services\Sales;

use App\Models\AccountReceivable;
use App\Models\AccountReceivablePayment;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleRefund;
use App\Models\StockMovement;
use App\Services\Inventory\StockMovementService;
use App\Services\AuditTrailService;
use App\Services\Cash\CashRegisterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class SaleService
{
    public function __construct(private readonly StockMovementService $stock, private readonly AuditTrailService $audit, private readonly CashRegisterService $cashRegister) {}

    public function create(int $companyId, array $data, ?int $userId): Sale
    {
        return DB::transaction(function () use ($companyId, $data, $userId): Sale {
            $company = Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();
            $existing = Sale::query()->where('request_key', $data['request_key'])->first();
            if ($existing) return $existing->load(['items', 'payments', 'receivables']);

            $customer = empty($data['customer_id']) ? null : Customer::query()->whereKey($data['customer_id'])->where('status', 'active')->lockForUpdate()->firstOrFail();
            $products = Product::query()->whereIn('id', collect($data['items'])->pluck('product_id')->unique())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $subtotal = 0;
            $normalized = [];
            $quantitiesByProduct = [];
            foreach ($data['items'] as $line) {
                $product = $products->get((int) $line['product_id']);
                if (! $product || $product->status !== 'active') throw ValidationException::withMessages(['items' => 'Um produto selecionado não está disponível.']);
                $quantity = round((float) $line['quantity'], 3);
                $quantitiesByProduct[$product->id] = ($quantitiesByProduct[$product->id] ?? 0) + $quantity;
                if ($quantity <= 0 || (float) $product->stock_quantity < $quantitiesByProduct[$product->id]) throw ValidationException::withMessages(['items' => 'Estoque insuficiente para '.$product->name.'.']);
                $lineTotal = (int) round($quantity * (int) $product->sale_price_cents);
                if ($lineTotal <= 0) throw ValidationException::withMessages(['items' => 'O produto '.$product->name.' precisa ter preço de venda cadastrado.']);
                $subtotal += $lineTotal;
                $normalized[] = compact('product', 'quantity', 'lineTotal');
            }

            $discount = (int) ($data['discount_cents'] ?? 0);
            if ($discount < 0 || $discount >= $subtotal) throw ValidationException::withMessages(['discount' => 'O desconto deve ser menor que o subtotal.']);
            $total = $subtotal - $discount;
            $paymentTotal = collect($data['payments'] ?? [])->sum(fn ($payment) => (int) $payment['amount_cents']);
            if ($paymentTotal > $total) throw ValidationException::withMessages(['payments' => 'A soma dos pagamentos não pode ultrapassar o total da venda.']);
            $balance = $total - $paymentTotal;
            if ($balance > 0 && ! $customer) throw ValidationException::withMessages(['customer_id' => 'Selecione um cliente para registrar saldo a receber.']);

            $saleNumber = (int) $company->last_sale_number + 1;
            $company->update(['last_sale_number' => $saleNumber]);
            $sale = Sale::create(['company_id' => $companyId, 'customer_id' => $customer?->id, 'sale_number' => $saleNumber, 'status' => 'completed', 'subtotal_cents' => $subtotal, 'discount_cents' => $discount, 'total_cents' => $total, 'paid_cents' => $paymentTotal, 'balance_cents' => $balance, 'request_key' => $data['request_key'], 'sold_at' => now(), 'created_by' => $userId]);

            foreach ($normalized as $line) {
                $product = $line['product'];
                SaleItem::create(['company_id' => $companyId, 'sale_id' => $sale->id, 'product_id' => $product->id, 'product_name' => $product->name, 'quantity' => $line['quantity'], 'unit_price_cents' => $product->sale_price_cents, 'unit_cost_cents' => $product->cost_price_cents, 'line_total_cents' => $line['lineTotal']]);
            }
            foreach ($products as $product) $this->stock->record($product, 'exit', $quantitiesByProduct[$product->id], 'Venda #'.$saleNumber, $userId);

            foreach ($data['payments'] ?? [] as $paymentIndex => $payment) {
                $row = SalePayment::create(['company_id' => $companyId, 'sale_id' => $sale->id, 'payment_method' => $payment['method'], 'amount_cents' => $payment['amount_cents'], 'reference' => $payment['reference'] ?? null, 'request_key' => $data['request_key'].':'.$paymentIndex, 'paid_at' => now()]);
                if ($payment['method'] === 'cash') $this->recordCashIn($row, $saleNumber, $userId);
            }

            if ($balance > 0) {
                $count = max(1, min(12, (int) ($data['installments'] ?? 1)));
                $firstDue = \Illuminate\Support\Carbon::parse($data['first_due_date'] ?? now()->toDateString())->startOfDay();
                $base = intdiv($balance, $count);
                $remainder = $balance % $count;
                for ($i = 1; $i <= $count; $i++) {
                    AccountReceivable::create(['company_id' => $companyId, 'sale_id' => $sale->id, 'customer_id' => $customer?->id, 'installment_number' => $i, 'amount_cents' => $base + ($i <= $remainder ? 1 : 0), 'paid_cents' => 0, 'due_date' => $firstDue->copy()->addMonthsNoOverflow($i - 1)->toDateString(), 'status' => 'pending']);
                }
            }
            return $sale->load(['items', 'payments', 'receivables']);
        });
    }

    public function cancel(int $saleId, string $reason, ?int $userId, ?int $approvedBy): Sale
    {
        return DB::transaction(function () use ($saleId, $reason, $userId, $approvedBy): Sale {
            $sale = Sale::query()->whereKey($saleId)->lockForUpdate()->firstOrFail();
            if ($sale->status === 'cancelled') return $sale->load(['items', 'payments', 'receivables.payments']);
            if ($sale->status !== 'completed') throw ValidationException::withMessages(['sale' => 'Esta venda não pode ser cancelada neste estado.']);

            $receivables = AccountReceivable::query()->where('sale_id', $sale->id)->orderBy('id')->lockForUpdate()->get();
            foreach ($receivables as $receivable) {
                if ($receivable->remainingCents() > 0 && $receivable->remainingCents() + (int) $receivable->paid_cents + (int) $receivable->cancelled_balance_cents !== (int) $receivable->amount_cents) {
                    throw ValidationException::withMessages(['sale' => 'O saldo das parcelas está inconsistente; o cancelamento foi interrompido.']);
                }
            }

            $items = SaleItem::query()->where('sale_id', $sale->id)->orderBy('product_id')->get();
            $soldMovements = StockMovement::query()->where('reason', 'Venda #'.$sale->sale_number)->where('movement_type', 'exit')->whereIn('product_id', $items->pluck('product_id'))->get()->groupBy('product_id');
            foreach ($items->groupBy('product_id') as $productId => $productItems) {
                $product = Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();
                $quantity = (float) $productItems->sum('quantity');
                $soldQty = abs((float) ($soldMovements[$productId] ?? collect())->sum('quantity_delta'));
                if ($soldQty + 0.0001 < $quantity) throw ValidationException::withMessages(['sale' => 'O histórico de estoque não confirma a baixa completa dos itens; cancelamento interrompido.']);
                $this->stock->record($product, 'entry', $quantity, 'Estorno da venda #'.$sale->sale_number, $userId, true);
            }

            foreach ($receivables as $receivable) {
                $remaining = $receivable->remainingCents();
                $cancelled = (int) $receivable->cancelled_balance_cents + $remaining;
                $receivable->update([
                    'cancelled_balance_cents' => $cancelled,
                    'status' => ((int) $receivable->paid_cents + $cancelled >= (int) $receivable->amount_cents) ? 'cancelled' : 'cancelled_partial',
                ]);
            }

            $sale->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $userId, 'cancelled_approved_by' => $approvedBy, 'cancellation_reason' => $reason, 'balance_cents' => 0]);
            $this->audit->record((int) $sale->company_id, 'sale.cancelled', 'Venda #'.$sale->sale_number.' cancelada: '.$reason, $userId, 'sale', (int) $sale->id, ['reason' => $reason], $approvedBy);
            return $sale->load(['items', 'payments', 'receivables.payments']);
        });
    }

    public function refund(int $saleId, array $data, ?int $userId, ?int $approvedBy): SaleRefund
    {
        return DB::transaction(function () use ($saleId, $data, $userId, $approvedBy): SaleRefund {
            $sale = Sale::query()->whereKey($saleId)->lockForUpdate()->firstOrFail();
            if ($sale->status !== 'cancelled') throw ValidationException::withMessages(['refund' => 'Cancele a venda antes de registrar uma devolução.']);
            $existing = SaleRefund::query()->where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ((int) $existing->sale_id !== $sale->id) throw ValidationException::withMessages(['refund' => 'Esta operação já foi registrada em outra venda.']);
                return $existing;
            }

            $paymentsTotal = (int) SalePayment::query()->where('sale_id', $sale->id)->sum('amount_cents')
                + (int) AccountReceivable::query()->where('sale_id', $sale->id)->sum('paid_cents');
            $refundedTotal = (int) SaleRefund::query()->where('sale_id', $sale->id)->sum('amount_cents');
            if ((int) $data['amount_cents'] > $paymentsTotal - $refundedTotal) {
                throw ValidationException::withMessages(['amount' => 'A devolução ultrapassa o valor efetivamente recebido e ainda não devolvido.']);
            }

            $receivedByMethod = (int) SalePayment::query()->where('sale_id', $sale->id)->where('payment_method', $data['payment_method'])->sum('amount_cents')
                + (int) AccountReceivablePayment::query()->whereHas('receivable', fn ($query) => $query->where('sale_id', $sale->id))->where('payment_method', $data['payment_method'])->sum('amount_cents');
            $refundedByMethod = (int) SaleRefund::query()->where('sale_id', $sale->id)->where('payment_method', $data['payment_method'])->sum('amount_cents');
            if ((int) $data['amount_cents'] > $receivedByMethod - $refundedByMethod) {
                throw ValidationException::withMessages(['payment_method' => 'O valor excede o total recebido por esta forma, descontadas as devoluções anteriores.']);
            }

            $session = null;
            if ($data['payment_method'] === 'cash') {
                $session = CashSession::query()->where('status', 'open')->orderByDesc('id')->lockForUpdate()->first();
                if (! $session) throw ValidationException::withMessages(['payment_method' => 'Abra o Caixa antes de devolver dinheiro em espécie.']);
                $cash = $this->cashBalance($session);
                if ((int) $data['amount_cents'] > $cash) throw ValidationException::withMessages(['amount' => 'O saldo disponível no Caixa é insuficiente para esta devolução.']);
            }

            $refund = SaleRefund::create([
                'company_id' => $sale->company_id,
                'sale_id' => $sale->id,
                'payment_method' => $data['payment_method'],
                'amount_cents' => $data['amount_cents'],
                'reference' => $data['reference'] ?? null,
                'reason' => $data['reason'],
                'request_key' => $data['request_key'],
                'cash_session_id' => $session?->id,
                'created_by' => $userId,
                'approved_by' => $approvedBy,
                'refunded_at' => $data['payment_method'] === 'cash' ? now() : Carbon::parse($data['refunded_at']),
            ]);

            if ($session) CashMovement::create([
                'company_id' => $sale->company_id,
                'cash_session_id' => $session->id,
                'direction' => 'out',
                'movement_type' => 'sale_refund',
                'amount_cents' => $data['amount_cents'],
                'description' => 'Devolução da venda #'.$sale->sale_number,
                'source_type' => 'sale_refund',
                'source_id' => $refund->id,
                'created_by' => $userId,
            ]);

            $sale->update(['paid_cents' => max(0, (int) $sale->paid_cents - (int) $data['amount_cents'])]);
            $this->audit->record((int) $sale->company_id, 'sale.refunded', 'Devolu��o da venda #'.$sale->sale_number, $userId, 'sale_refund', (int) $refund->id, ['amount_cents' => (int) $refund->amount_cents, 'payment_method' => $refund->payment_method, 'cash_session_id' => $refund->cash_session_id, 'reason' => $refund->reason], $approvedBy);
            return $refund;
        });
    }

    private function cashBalance(CashSession $session): int
    {
        $totals = CashMovement::query()->where('cash_session_id', $session->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount_cents ELSE 0 END), 0) as total_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN amount_cents ELSE 0 END), 0) as total_out")
            ->first();
        return (int) $session->opening_amount_cents + (int) $totals->total_in - (int) $totals->total_out;
    }

    private function recordCashIn(SalePayment $payment, int $saleNumber, ?int $userId): void
    {
        $session = CashSession::query()->where('status', 'open')->orderByDesc('id')->lockForUpdate()->first();
        if (! $session) throw ValidationException::withMessages(['payments' => 'Abra o Caixa antes de receber pagamentos em dinheiro.']);
        $this->cashRegister->assertIncomingFits($session, (int) $payment->amount_cents);
        CashMovement::create(['company_id' => $session->company_id, 'cash_session_id' => $session->id, 'direction' => 'in', 'movement_type' => 'sale_payment', 'amount_cents' => $payment->amount_cents, 'description' => 'Recebimento da venda #'.$saleNumber, 'source_type' => 'sale_payment', 'source_id' => $payment->id, 'created_by' => $userId]);
    }
}
