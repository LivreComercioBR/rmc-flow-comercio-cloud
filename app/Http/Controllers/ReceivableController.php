<?php

namespace App\Http\Controllers;

use App\Models\AccountReceivable;
use App\Models\AccountReceivablePayment;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\Category;
use App\Models\ServiceWorkOrder;
use App\Services\AuditTrailService;
use App\Support\CompanyContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ReceivableController extends Controller
{
    public function index(Request $request, CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $companyId = $context->id();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['open', 'overdue', 'paid'])],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $today = now()->toDateString();
        $cashSessionOpen = CashSession::query()->where('status', 'open')->where('opened_by', $request->user()->id)->exists();
        $role = $request->user()->companyMemberships()->where('company_id', $context->id())->where('status', 'active')->value('role');
        $receivables = AccountReceivable::query()->with(['sale.customer', 'customer', 'category', 'payments.creator', 'workOrder'])->orderByDesc('id')
            ->when(($filters['status'] ?? null) === 'open', fn ($q) => $q->whereIn('status', ['pending', 'partial']))
            ->when(($filters['status'] ?? null) === 'overdue', fn ($q) => $q->whereIn('status', ['pending', 'partial'])->where('due_date', '<', $today))
            ->when(($filters['status'] ?? null) === 'paid', fn ($q) => $q->where('status', 'paid'))
            ->when($search !== '', fn ($q) => $q->where(function ($searchQuery) use ($search): void {
                $searchQuery->where('description', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$search.'%')->orWhere('document', 'like', '%'.$search.'%')->orWhere('phone', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))
                    ->orWhereHas('sale', fn ($sale) => $sale->where('sale_number', 'like', '%'.$search.'%')->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$search.'%')->orWhere('document', 'like', '%'.$search.'%')->orWhere('phone', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')));
            }))
            ->when(! empty($filters['customer_id']), fn ($q) => $q->where(function ($customerQuery) use ($filters): void {
                $customerQuery->where('customer_id', $filters['customer_id'])
                    ->orWhereHas('sale', fn ($sale) => $sale->where('customer_id', $filters['customer_id']));
            }))
            ->orderByRaw("CASE WHEN status IN ('pending','partial') AND due_date < ? THEN 0 ELSE 1 END", [$today])
            ->orderBy('due_date')->orderBy('id')->paginate(25)->withQueryString();
        $requestKeys = $receivables->getCollection()->mapWithKeys(fn ($row) => [$row->id => (string) Str::uuid()]);
        return view('receivables.index', compact('receivables', 'filters', 'search', 'today', 'cashSessionOpen', 'requestKeys', 'role'));
    }

    public function create(Request $request, CompanyContext $context): View
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);
        $customerId = Customer::query()->where('company_id', $companyId)->where('status', 'active')->whereKey($request->query('customer_id'))->value('id');
        return view('receivables.create', ['customers' => Customer::query()->where('status', 'active')->orderBy('name')->get(), 'categories' => Category::query()->where('kind', 'revenue')->where('status', 'active')->orderBy('name')->get(), 'requestKey' => (string) Str::uuid(), 'customerId' => $customerId]);
    }

    public function storeManual(Request $request, CompanyContext $context): RedirectResponse
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);
        $data = $request->validate([
            'request_key' => ['required', 'uuid'], 'description' => ['required', 'string', 'max:180'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'financial_category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('company_id', $companyId)->where('kind', 'revenue')],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
            'installments' => ['required', 'integer', 'min:1', 'max:24'], 'first_due_date' => ['required', 'date'],
        ]);
        $existing = AccountReceivable::query()->where('manual_request_key', 'like', $data['request_key'].':%')->first();
        if (! $existing) {
            $amount = (int) round((float) str_replace(',', '.', $data['amount']) * 100);
            $count = (int) $data['installments']; $base = intdiv($amount, $count); $remainder = $amount % $count;
            if ($base < 1) return back()->withInput()->withErrors(['amount' => 'O valor total precisa cobrir ao menos um centavo por parcela.']);
            DB::transaction(function () use ($data, $companyId, $request, $count, $base, $remainder): void {
                \App\Models\Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();
                if (AccountReceivable::query()->where('manual_request_key', 'like', $data['request_key'].':%')->exists()) return;
                $firstDue = Carbon::parse($data['first_due_date'])->startOfDay();
                for ($i = 1; $i <= $count; $i++) AccountReceivable::create([
                    'company_id' => $companyId, 'sale_id' => null, 'customer_id' => $data['customer_id'] ?? null, 'description' => trim($data['description']),
                    'financial_category_id' => $data['financial_category_id'] ?? null, 'manual_request_key' => $data['request_key'].':'.$i,
                    'installment_number' => $i, 'amount_cents' => $base + ($i <= $remainder ? 1 : 0), 'paid_cents' => 0,
                    'cancelled_balance_cents' => 0, 'due_date' => $firstDue->copy()->addMonthsNoOverflow($i - 1)->toDateString(), 'status' => 'pending',
                ]);
            });
        }
        $returnFilters = ! empty($data['customer_id']) ? ['status' => 'open', 'customer_id' => $data['customer_id']] : [];
        return redirect()->route('receivables.index', $returnFilters)->with('receivable_notice', 'Lançamento manual de receita criado em parcelas.');
    }

    public function pay(Request $request, int $receivable, CompanyContext $context, AuditTrailService $audit): RedirectResponse
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'payment_method' => ['required', Rule::in(['cash', 'pix', 'credit_card', 'debit_card', 'bank_transfer', 'other'])],
            'reference' => ['nullable', 'string', 'max:120'],
            'return_status' => ['nullable', Rule::in(['open', 'overdue', 'paid'])],
            'return_customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'return_q' => ['nullable', 'string', 'max:100'],
        ]);
        $amountCents = (int) round((float) str_replace(',', '.', $data['amount']) * 100);
        try {
            DB::transaction(function () use ($receivable, $companyId, $data, $amountCents, $request, $audit): void {
                $existing = AccountReceivablePayment::query()->where('request_key', $data['request_key'])->first();
                if ($existing) {
                    if ((int) $existing->account_receivable_id !== $receivable) throw ValidationException::withMessages(['payment' => 'Esta operação já foi registrada. Atualize a página e tente novamente.']);
                    return;
                }
                $snapshot = AccountReceivable::query()->whereKey($receivable)->firstOrFail();
                $workOrder = $snapshot->service_work_order_id
                    ? ServiceWorkOrder::query()->whereKey($snapshot->service_work_order_id)->lockForUpdate()->firstOrFail()
                    : null;
                $record = AccountReceivable::query()->whereKey($receivable)->lockForUpdate()->firstOrFail();
                $remaining = $record->remainingCents();
                if (! in_array($record->status, ['pending', 'partial'], true) || $amountCents > $remaining || $workOrder?->status === 'cancelled') {
                    throw ValidationException::withMessages(['amount' => 'O valor excede o saldo em aberto desta parcela ou a OS foi cancelada.']);
                }
                $sale = $record->sale_id ? Sale::query()->whereKey($record->sale_id)->lockForUpdate()->first() : null;
                $session = null;
                if ($data['payment_method'] === 'cash') {
                    $session = CashSession::query()->where('status', 'open')->where('opened_by', $request->user()->id)->orderByDesc('id')->lockForUpdate()->first();
                    if (! $session) throw ValidationException::withMessages(['payment_method' => 'Abra o Caixa antes de registrar recebimento em dinheiro.']);
                    app(\App\Services\Cash\CashRegisterService::class)->assertIncomingFits($session, $amountCents);
                }
                $payment = AccountReceivablePayment::create(['company_id' => $companyId, 'account_receivable_id' => $record->id, 'payment_method' => $data['payment_method'], 'amount_cents' => $amountCents, 'cash_session_id' => $session?->id, 'reference' => $data['reference'] ?? null, 'paid_at' => $data['payment_method'] === 'cash' ? now() : Carbon::parse($data['paid_at']), 'request_key' => $data['request_key'], 'created_by' => $request->user()->id]);
                if ($session) CashMovement::create(['company_id' => $companyId, 'cash_session_id' => $session->id, 'direction' => 'in', 'movement_type' => 'receivable_payment', 'amount_cents' => $amountCents, 'description' => 'Recebimento '.($workOrder ? 'OS #'.$workOrder->work_order_number : ($record->sale_id ? 'venda #'.($sale?->sale_number ?? $record->sale_id) : 'conta #'.$record->id)).' parcela '.$record->installment_number, 'source_type' => 'account_receivable_payment', 'source_id' => $payment->id, 'created_by' => $request->user()->id]);
                $paid = (int) $record->paid_cents + $amountCents;
                $record->update(['paid_cents' => $paid, 'status' => $paid >= (int) $record->amount_cents ? 'paid' : 'partial']);
                if ($workOrder && $paid >= (int) $record->amount_cents && $workOrder->status === 'awaiting_payment') {
                    $workOrder->update(['status' => 'ready']);
                    $audit->record($companyId, 'service.work_order_status_changed', 'OS #'.$workOrder->work_order_number.' liberada para retirada após pagamento integral.', (int) $request->user()->id, 'service_work_order', (int) $workOrder->id, ['before' => 'awaiting_payment', 'after' => 'ready', 'receivable_id' => (int) $record->id]);
                }
                if ($sale) {
                    $salePaid = (int) $sale->paid_cents + $amountCents;
                    $sale->update(['paid_cents' => $salePaid, 'balance_cents' => max(0, (int) $sale->total_cents - $salePaid)]);
                }
            });
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        } catch (QueryException $exception) {
            $existing = AccountReceivablePayment::query()->where('request_key', $data['request_key'])->first();
            if (! $existing || (int) $existing->account_receivable_id !== $receivable) throw $exception;
        }
        $workOrderId = AccountReceivable::query()->whereKey($receivable)->value('service_work_order_id');
        if ($workOrderId) return redirect()->route('services.work-orders.show', $workOrderId)->with('work_order_notice', 'Recebimento registrado na OS.');
        $returnFilters = array_filter([
            'status' => $data['return_status'] ?? null,
            'customer_id' => $data['return_customer_id'] ?? null,
            'q' => trim((string) ($data['return_q'] ?? '')),
        ], fn ($value) => $value !== null && $value !== '');
        return redirect()->route('receivables.index', $returnFilters)->with('receivable_notice', 'Recebimento registrado.');
    }
}
