<?php

namespace App\Http\Controllers;

use App\Models\AccountPayable;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Services\Inventory\StockMovementService;
use App\Support\CompanyContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class PurchaseController extends Controller
{
    private const MAX_CENTS = 4294967295;

    public function index(Request $request, CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $companyId = $context->id();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['completed', 'cancelled'])],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $suppliers = Supplier::query()->orderBy('name')->get();
        $purchases = Purchase::query()->with(['supplier', 'payables'])
            ->when($search !== '', fn ($query) => $query->where(function ($matches) use ($search): void {
                $matches->where('invoice_number', 'like', '%'.$search.'%')->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', '%'.$search.'%'));
            }))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['supplier_id']), fn ($query) => $query->where('supplier_id', $filters['supplier_id']))
            ->when(! empty($filters['from']), fn ($query) => $query->whereDate('purchased_at', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($query) => $query->whereDate('purchased_at', '<=', $filters['to']))
            ->latest('purchased_at')->latest('id')->paginate(20)->withQueryString();
        return view('purchases.index', compact('purchases', 'search', 'filters', 'suppliers'));
    }

    public function create(Request $request, CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $supplierId = Supplier::query()->where('status', 'active')->whereKey($request->query('supplier_id'))->value('id');
        return view('purchases.create', [
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->get(),
            'products' => Product::query()->where('status', 'active')->orderBy('name')->get(),
            'supplierId' => $supplierId,
            'requestKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, CompanyContext $context, StockMovementService $stock): RedirectResponse
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('status', 'active'))],
            'invoice_number' => ['nullable', 'string', 'max:64'],
            'purchased_at' => ['required', 'date', 'before_or_equal:today'],
            'first_due_date' => ['required', 'date', 'after_or_equal:purchased_at'],
            'installment_count' => ['required', 'integer', 'min:1', 'max:36'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('status', 'active'))],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'regex:/^\d+(?:[.,]\d{1,3})?$/'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:42949672.95', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
        ]);

        $lines = [];
        $totalCents = 0;
        foreach ($data['items'] as $item) {
            $quantity = (float) str_replace(',', '.', $item['quantity']);
            $unitCostCents = $this->toCents($item['unit_cost']);
            $lineCents = (int) round($quantity * $unitCostCents);
            if ($lineCents <= 0 || $lineCents > self::MAX_CENTS - $totalCents) {
                throw ValidationException::withMessages(['items' => 'O total da compra deve ser positivo e estar dentro do limite permitido.']);
            }
            $totalCents += $lineCents;
            $lines[] = ['product_id' => (int) $item['product_id'], 'quantity' => $quantity, 'unit_cost_cents' => $unitCostCents, 'total_cents' => $lineCents];
        }
        $installments = (int) $data['installment_count'];
        if ($installments > $totalCents) {
            throw ValidationException::withMessages(['installment_count' => 'A quantidade de parcelas não pode gerar parcelas sem valor.']);
        }

        try {
            $purchase = DB::transaction(function () use ($data, $lines, $totalCents, $installments, $companyId, $stock, $request): Purchase {
                $supplier = Supplier::query()->whereKey($data['supplier_id'])->where('status', 'active')->lockForUpdate()->first();
                if (! $supplier) {
                    throw ValidationException::withMessages(['supplier_id' => 'O fornecedor foi desativado. Atualize a página e selecione outro fornecedor.']);
                }
                $productIds = collect($lines)->pluck('product_id')->sort()->values();
                $lockedProducts = Product::query()->whereIn('id', $productIds)->where('status', 'active')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                if ($lockedProducts->count() !== $productIds->count()) {
                    throw ValidationException::withMessages(['items' => 'Um ou mais produtos foram desativados. Atualize a compra e tente novamente.']);
                }

                $purchase = Purchase::create([
                    'company_id' => $companyId,
                    'supplier_id' => $supplier->id,
                    'invoice_number' => $data['invoice_number'] ?? null,
                    'purchased_at' => $data['purchased_at'],
                    'total_cents' => $totalCents,
                    'installment_count' => $installments,
                    'status' => 'completed',
                    'request_key' => $data['request_key'],
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                foreach ($lines as $line) {
                    $product = $lockedProducts->get($line['product_id']);
                    PurchaseItem::create($line + [
                        'company_id' => $companyId,
                        'purchase_id' => $purchase->id,
                        'previous_cost_price_cents' => $product->cost_price_cents,
                    ]);
                    $product->update(['cost_price_cents' => $line['unit_cost_cents']]);
                    $stock->record($product, 'entry', $line['quantity'], 'Entrada pela compra #'.$purchase->id, $request->user()->id);
                }

                $baseCents = intdiv($totalCents, $installments);
                $remainderCents = $totalCents % $installments;
                for ($number = 1; $number <= $installments; $number++) {
                    $amountCents = $baseCents + ($number <= $remainderCents ? 1 : 0);
                    AccountPayable::create([
                        'company_id' => $companyId,
                        'purchase_id' => $purchase->id,
                        'supplier_id' => $supplier->id,
                        'description' => 'Compra #'.$purchase->id.' - parcela '.$number.'/'.$installments,
                        'installment_number' => $number,
                        'due_date' => Carbon::parse($data['first_due_date'])->addMonthsNoOverflow($number - 1)->toDateString(),
                        'amount_cents' => $amountCents,
                        'status' => 'pending',
                        'created_by' => $request->user()->id,
                    ]);
                }
                return $purchase;
            });
        } catch (QueryException $exception) {
            $existing = Purchase::query()->where('request_key', $data['request_key'])->first();
            if ($existing) return redirect()->route('purchases.show', $existing->id)->with('purchase_notice', 'Esta compra já havia sido registrada.');
            if ($exception->getCode() === '23000') {
                return back()->withInput()->withErrors(['invoice_number' => 'Esse número de nota já está registrado para este fornecedor.']);
            }
            throw $exception;
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return redirect()->route('purchases.show', $purchase->id)->with('purchase_notice', 'Compra registrada, estoque atualizado e parcelas a pagar geradas.');
    }

    public function show(int $purchase, CompanyContext $context): View
    {
        $record = $this->findForCompany($purchase, $context);
        $record->load(['supplier', 'creator', 'items.product', 'payables.payments.creator']);
        return view('purchases.show', ['purchase' => $record]);
    }

    public function cancel(Request $request, int $purchase, CompanyContext $context, StockMovementService $stock): RedirectResponse
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);
        $data = $request->validate(['cancellation_reason' => ['required', 'string', 'max:180']]);
        try {
            DB::transaction(function () use ($purchase, $companyId, $stock, $request, $data): void {
                $record = Purchase::query()->whereKey($purchase)->lockForUpdate()->firstOrFail();
                if ($record->status !== 'completed') {
                    throw ValidationException::withMessages(['cancellation' => 'Somente compras concluídas podem ser canceladas.']);
                }
                $items = $record->items()->orderBy('product_id')->get();
                foreach ($items as $item) {
                    $stock->record($item->product, 'exit', (float) $item->quantity, 'Estorno da compra #'.$record->id, $request->user()->id);
                    $laterCompletedPurchaseExists = PurchaseItem::query()->where('product_id', $item->product_id)
                        ->where('purchase_id', '>', $record->id)
                        ->whereHas('purchase', fn ($query) => $query->where('status', 'completed'))
                        ->exists();
                    $currentProduct = Product::query()->whereKey($item->product_id)->firstOrFail();
                    if (! $laterCompletedPurchaseExists && (int) $currentProduct->cost_price_cents === (int) $item->unit_cost_cents) {
                        $currentProduct->update(['cost_price_cents' => $item->previous_cost_price_cents]);
                    }
                }
                $payables = AccountPayable::query()->where('purchase_id', $record->id)->orderBy('id')->lockForUpdate()->get();
                foreach ($payables as $payable) {
                    $remaining = $payable->remainingCents();
                    if ($remaining <= 0) continue;
                    $payable->update([
                        'cancelled_balance_cents' => (int) $payable->cancelled_balance_cents + $remaining,
                        'status' => (int) $payable->paid_cents > 0 ? 'cancelled_partial' : 'cancelled',
                    ]);
                }
                $record->update([
                    'status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $request->user()->id,
                    'cancellation_reason' => $data['cancellation_reason'],
                ]);
            });
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Não foi possível cancelar a compra.';
            return back()->withErrors(['cancellation' => $message]);
        }
        return redirect()->route('purchases.show', $purchase)->with('purchase_notice', 'Compra cancelada. O estoque foi estornado; parcelas em aberto foram canceladas e pagamentos já feitos foram preservados.');
    }

    private function findForCompany(int $purchase, CompanyContext $context): Purchase
    {
        abort_unless($context->id(), 403);
        return Purchase::query()->findOrFail($purchase);
    }

    private function toCents(mixed $value): int
    {
        return (int) round((float) str_replace(',', '.', (string) ($value ?: '0')) * 100);
    }
}
