<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Sales\SaleService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\CashSession;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class SaleController extends Controller
{
    public function index(CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        return view('sales.index', ['sales' => Sale::query()->with('customer')->latest('sold_at')->paginate(20)]);
    }

    public function create(Request $request, CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $customerId = Customer::query()->where('status', 'active')->whereKey($request->query('customer_id'))->value('id');
        return view('sales.create', ['products' => Product::query()->where('status', 'active')->where('stock_quantity', '>', 0)->orderBy('name')->get(), 'customers' => Customer::query()->where('status', 'active')->orderBy('name')->get(), 'customerId' => $customerId, 'requestKey' => (string) Str::uuid()]);
    }

    public function store(Request $request, CompanyContext $context, SaleService $sales): RedirectResponse
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999999999'],
            'payments' => ['nullable', 'array', 'max:8'],
            'payments.*.method' => ['nullable', 'required_with:payments.*.amount', Rule::in(['cash', 'pix', 'debit_card', 'credit_card', 'bank_transfer', 'other'])],
            'payments.*.amount' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
            'payments.*.reference' => ['nullable', 'string', 'max:120'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:12'],
            'first_due_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $data['discount_cents'] = $this->toCents($data['discount'] ?? 0);
        $data['payments'] = collect($data['payments'] ?? [])->filter(fn ($payment) => ! empty($payment['method']) && ! empty($payment['amount']))->map(fn ($payment) => ['method' => $payment['method'], 'amount_cents' => $this->toCents($payment['amount']), 'reference' => $payment['reference'] ?? null])->values()->all();
        try {
            $sale = $sales->create($companyId, $data, $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }
        return redirect()->route('sales.show', $sale)->with('sale_notice', 'Venda #'.$sale->sale_number.' registrada.');
    }

    public function show(int $sale, CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $sale = Sale::query()->findOrFail($sale);
        $sale->load(['customer', 'items', 'payments', 'receivables.payments', 'refunds.creator', 'refunds.approver', 'cancelledBy', 'cancellationApprover']);
        $methodNames = ['cash' => 'Dinheiro', 'pix' => 'PIX', 'debit_card' => 'Cartão de débito', 'credit_card' => 'Cartão de crédito', 'bank_transfer' => 'Transferência', 'other' => 'Outro'];
        $received = $sale->payments->groupBy('payment_method')->map(fn ($items) => (int) $items->sum('amount_cents'));
        foreach ($sale->receivables as $receivable) {
            foreach ($receivable->payments as $payment) $received[$payment->payment_method] = (int) ($received[$payment->payment_method] ?? 0) + (int) $payment->amount_cents;
        }
        foreach ($sale->refunds->groupBy('payment_method') as $method => $refunds) $received[$method] = max(0, (int) ($received[$method] ?? 0) - (int) $refunds->sum('amount_cents'));
        $refundOptions = $received->filter(fn ($amount) => $amount > 0)->mapWithKeys(fn ($amount, $method) => [$method => ['label' => $methodNames[$method] ?? $method, 'remaining_cents' => (int) $amount]]);

        $cashSessionOpen = CashSession::query()->where('status', 'open')->exists();
        if (! $cashSessionOpen) $refundOptions = $refundOptions->except('cash');
        return view('sales.show', ['sale' => $sale, 'refundOptions' => $refundOptions, 'cashSessionOpen' => $cashSessionOpen, 'refundRequestKey' => (string) Str::uuid(), 'role' => request()->user()->companyMemberships()->where('company_id', app(CompanyContext::class)->id())->where('status', 'active')->value('role')]);
    }

    public function cancel(Request $request, Sale $sale, SaleService $sales): RedirectResponse
    {
        $credentials = $this->extractApprovalCredentials($request);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:180']]);
        try {
            $approverId = $this->authorizeCancellation($request, $credentials);
            $sales->cancel($sale->id, $data['reason'], $request->user()->id, $approverId);
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }
        return redirect()->route('sales.show', $sale)->with('sale_notice', 'Venda cancelada e estoque estornado. Recebimentos anteriores foram preservados.');
    }

    public function refund(Request $request, Sale $sale, SaleService $sales): RedirectResponse
    {
        $credentials = $this->extractApprovalCredentials($request);
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
            'payment_method' => ['required', Rule::in(['cash', 'pix', 'credit_card', 'debit_card', 'bank_transfer', 'other'])],
            'refunded_at' => ['required', 'date', 'before_or_equal:now'],
            'reference' => ['nullable', 'string', 'max:120'],
            'reason' => ['required', 'string', 'min:5', 'max:180'],
        ]);
        $data['amount_cents'] = $this->toCents($data['amount']);
        try {
            $approverId = $this->authorizeCancellation($request, $credentials);
            $sales->refund($sale->id, $data, $request->user()->id, $approverId);
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }
        return redirect()->route('sales.show', $sale)->with('sale_notice', 'Devolução registrada sem apagar o pagamento original.');
    }

    private function toCents(mixed $value): int
    {
        return (int) round((float) str_replace(',', '.', (string) ($value ?: '0')) * 100);
    }

    private function extractApprovalCredentials(Request $request): array
    {
        $credentials = [
            'email' => trim((string) $request->input('approval_email', '')),
            'password' => (string) $request->input('approval_password', $request->input('current_password', '')),
        ];
        $request->request->remove('approval_email');
        $request->request->remove('approval_password');
        $request->request->remove('current_password');
        return $credentials;
    }

    private function authorizeCancellation(Request $request, array $credentials): int
    {
        $companyId = app(CompanyContext::class)->id();
        $actor = $request->user();
        $membership = $actor->companyMemberships()->where('company_id', $companyId)->where('status', 'active')->firstOrFail();

        if ($membership->role === 'Administrador') {
            if ($credentials['password'] === '' || ! Hash::check($credentials['password'], $actor->password)) {
                throw ValidationException::withMessages(['current_password' => 'Confirme sua senha de administrador para autorizar a operação.']);
            }
            return (int) $actor->id;
        }

        if ($membership->role !== 'Atendente' || $credentials['email'] === '' || $credentials['password'] === '') {
            throw ValidationException::withMessages(['approval_password' => 'Informe o e-mail e a senha de um administrador ativo para liberar a operação.']);
        }
        $approver = User::query()->where('email', mb_strtolower($credentials['email']))->where('status', 'active')->first();
        $isCompanyAdmin = $approver && $approver->companyMemberships()->where('company_id', $companyId)->where('role', 'Administrador')->where('status', 'active')->exists();
        if (! $isCompanyAdmin || ! Hash::check($credentials['password'], $approver->password)) {
            throw ValidationException::withMessages(['approval_password' => 'Não foi possível validar a autorização do administrador.']);
        }
        return (int) $approver->id;
    }
}
