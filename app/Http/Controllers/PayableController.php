<?php

namespace App\Http\Controllers;

use App\Models\AccountPayable;
use App\Models\AccountPayablePayment;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Supplier;
use App\Services\Cash\CashRegisterService;
use App\Support\CompanyContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class PayableController extends Controller
{
    public function index(Request $request, CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $filters = $request->validate(['status' => ['nullable', Rule::in(['open', 'overdue', 'paid', 'cancelled'])]]);
        $today = now()->toDateString();
        $cashSessionOpen = CashSession::query()->where('status', 'open')->exists();
        $payables = AccountPayable::query()->with(['supplier', 'category', 'purchase', 'payments.creator'])
            ->when(($filters['status'] ?? null) === 'open', fn ($query) => $query->whereIn('status', ['pending', 'partial']))
            ->when(($filters['status'] ?? null) === 'overdue', fn ($query) => $query->whereIn('status', ['pending', 'partial'])->where('due_date', '<', $today))
            ->when(($filters['status'] ?? null) === 'paid', fn ($query) => $query->where('status', 'paid'))
            ->when(($filters['status'] ?? null) === 'cancelled', fn ($query) => $query->whereIn('status', ['cancelled', 'cancelled_partial']))
            ->orderByRaw("CASE WHEN status IN ('pending','partial') AND due_date < ? THEN 0 ELSE 1 END", [$today])
            ->orderBy('due_date')->orderBy('id')->paginate(25)->withQueryString();
        return view('payables.index', compact('payables', 'filters', 'today', 'cashSessionOpen'));
    }

    public function create(Request $request, CompanyContext $context): View
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);
        $supplierId = Supplier::query()->where('company_id', $companyId)->where('status', 'active')->whereKey($request->query('supplier_id'))->value('id');
        return view('payables.create', ['suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->get(), 'categories' => Category::query()->where('kind', 'expense')->where('status', 'active')->orderBy('name')->get(), 'requestKey' => (string) \Illuminate\Support\Str::uuid(), 'supplierId' => $supplierId]);
    }

    public function storeManual(Request $request, CompanyContext $context): RedirectResponse
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);
        $data = $request->validate([
            'request_key' => ['required', 'uuid'], 'description' => ['required', 'string', 'max:180'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'financial_category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('company_id', $companyId)->where('kind', 'expense')],
            'amount' => ['required', 'numeric', 'gt:0', 'max:42949672.95', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
            'installments' => ['required', 'integer', 'min:1', 'max:24'],
            'due_dates' => ['required', 'array', 'size:'.$request->input('installments')],
            'due_dates.*' => ['required', 'date_format:Y-m-d'],
        ]);
        $existing = AccountPayable::query()->where('manual_request_key', 'like', $data['request_key'].':%')->first();
        if (! $existing) {
            $amount = (int) round((float) str_replace(',', '.', $data['amount']) * 100);
            $count = (int) $data['installments']; $base = intdiv($amount, $count); $remainder = $amount % $count;
            if ($base < 1) return back()->withInput()->withErrors(['amount' => 'O valor total precisa cobrir ao menos um centavo por parcela.']);
            DB::transaction(function () use ($data, $companyId, $request, $count, $base, $remainder): void {
                \App\Models\Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();
                if (AccountPayable::query()->where('manual_request_key', 'like', $data['request_key'].':%')->exists()) return;
                for ($i = 1; $i <= $count; $i++) AccountPayable::create([
                    'company_id' => $companyId, 'purchase_id' => null, 'supplier_id' => $data['supplier_id'] ?? null,
                    'financial_category_id' => $data['financial_category_id'] ?? null, 'manual_request_key' => $data['request_key'].':'.$i,
                    'description' => $data['description'], 'installment_number' => $i, 'due_date' => $data['due_dates'][$i - 1],
                    'amount_cents' => $base + ($i <= $remainder ? 1 : 0), 'paid_cents' => 0, 'cancelled_balance_cents' => 0,
                    'status' => 'pending', 'created_by' => $request->user()->id,
                ]);
            });
        }
        return redirect()->route('payables.index')->with('payable_notice', 'Lançamento manual de despesa criado em parcelas.');
    }

    public function pay(Request $request, int $payable, CompanyContext $context, CashRegisterService $cash): RedirectResponse
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:42949672.95', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'payment_method' => ['required', Rule::in(['cash', 'pix', 'credit_card', 'debit_card', 'bank_transfer', 'other'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $amountCents = (int) round((float) str_replace(',', '.', $data['amount']) * 100);

        try {
            DB::transaction(function () use ($payable, $companyId, $data, $amountCents, $request, $cash): void {
                $existing = AccountPayablePayment::query()->where('request_key', $data['request_key'])->first();
                if ($existing) {
                    if ((int) $existing->account_payable_id !== $payable) {
                        throw ValidationException::withMessages(['payment' => 'Esta solicitação de pagamento já foi usada. Atualize a página antes de tentar novamente.']);
                    }
                    return;
                }

                $record = AccountPayable::query()->whereKey($payable)->lockForUpdate()->firstOrFail();
                $remaining = $record->remainingCents();
                if (! in_array($record->status, ['pending', 'partial'], true) || $amountCents > $remaining) {
                    throw ValidationException::withMessages(['amount' => 'O valor informado excede o saldo em aberto ou a parcela não pode mais receber pagamentos.']);
                }

                $payment = AccountPayablePayment::create([
                    'company_id' => $companyId,
                    'account_payable_id' => $record->id,
                    'amount_cents' => $amountCents,
                    'paid_at' => $data['payment_method'] === 'cash' ? now() : Carbon::parse($data['paid_at']),
                    'payment_method' => $data['payment_method'],
                    'request_key' => $data['request_key'],
                    'reference' => $data['reference'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                if ($data['payment_method'] === 'cash') {
                    $session = $cash->recordPayablePayment($payment, $request->user()->id);
                    $payment->update(['cash_session_id' => $session->id]);
                }

                $paidCents = (int) $record->paid_cents + $amountCents;
                $record->update([
                    'paid_cents' => $paidCents,
                    'status' => $paidCents + (int) $record->cancelled_balance_cents >= (int) $record->amount_cents ? 'paid' : 'partial',
                ]);
            });
        } catch (QueryException $exception) {
            $existing = AccountPayablePayment::query()->where('request_key', $data['request_key'])->first();
            if (! $existing || (int) $existing->account_payable_id !== $payable) throw $exception;
        }

        return redirect()->route('payables.index')->with('payable_notice', 'Pagamento registrado.');
    }
}
