<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\AccountReceivable;
use App\Models\ServiceAsset;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CustomerController extends Controller
{
    public function index(Request $request, CompanyContext $context): View
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);

        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = trim((string) ($filters['q'] ?? ''));
        $customers = Customer::query()->where('company_id', $companyId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($matches) use ($search): void {
                    $matches->where('name', 'like', '%'.$search.'%')
                        ->orWhere('document', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('customers.index', compact('customers', 'search'));
    }

    public function create(Request $request): View
    {
        $returnTo = $request->validate(['return_to' => ['nullable', Rule::in(['receivables.manual.create', 'services.appointments.create'])]])['return_to'] ?? null;
        return view('customers.create', compact('returnTo'));
    }

    public function show(int $customer, CompanyContext $context): View
    {
        $record = $this->findForCompany($customer, $context);
        $sales = $record->sales()->with(['items', 'payments', 'receivables'])->paginate(15);
        $receivables = AccountReceivable::query()->with(['sale', 'payments'])
            ->where(function ($query) use ($record): void {
                $query->where('customer_id', $record->id)
                    ->orWhereHas('sale', fn ($sale) => $sale->where('customer_id', $record->id));
            })->whereIn('status', ['pending', 'partial'])->orderBy('due_date')->get();
        $openReceivableCents = $receivables->sum(fn ($row) => $row->remainingCents());
        $servicesEnabled = $record->company->modules()->where('module_key', 'services')->where('enabled', true)->exists();
        $assets = $servicesEnabled ? ServiceAsset::query()->where('customer_id', $record->id)->with(['workOrders' => fn ($query) => $query->orderByDesc('opened_at')->limit(10)])->withCount('workOrders')->orderBy('name')->get() : collect();
        return view('customers.show', compact('customer', 'record', 'sales', 'receivables', 'openReceivableCents', 'servicesEnabled', 'assets'));
    }

    public function store(Request $request, CompanyContext $context): RedirectResponse
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);

        $data = $this->validated($request, $companyId);
        $returnTo = $request->validate(['return_to' => ['nullable', Rule::in(['receivables.manual.create', 'services.appointments.create'])]])['return_to'] ?? null;
        $customer = Customer::create($data + ['company_id' => $companyId, 'status' => 'active']);

        if ($returnTo) return redirect()->route($returnTo, ['customer_id' => $customer->id]);

        return redirect()->route('customers.index')->with('customer_notice', 'Cliente cadastrado.');
    }

    public function edit(int $customer, CompanyContext $context): View
    {
        $record = $this->findForCompany($customer, $context);
        return view('customers.edit', ['customer' => $record, 'returnTo' => null]);
    }

    public function update(Request $request, int $customer, CompanyContext $context): RedirectResponse
    {
        $record = $this->findForCompany($customer, $context);
        $record->update($this->validated($request, $context->id(), $record->id));

        return redirect()->route('customers.index')->with('customer_notice', 'Cadastro do cliente atualizado.');
    }

    public function deactivate(int $customer, CompanyContext $context): RedirectResponse
    {
        $record = $this->findForCompany($customer, $context);
        $record->update(['status' => 'inactive']);

        return redirect()->route('customers.index')->with('customer_notice', 'Cliente desativado. O histórico permanece preservado.');
    }

    public function activate(int $customer, CompanyContext $context): RedirectResponse
    {
        $record = $this->findForCompany($customer, $context);
        $record->update(['status' => 'active']);

        return redirect()->route('customers.index')->with('customer_notice', 'Cliente reativado.');
    }

    private function findForCompany(int $customer, CompanyContext $context): Customer
    {
        abort_unless($context->id(), 403);
        return Customer::query()->where('company_id', $context->id())->findOrFail($customer);
    }

    private function validated(Request $request, ?int $companyId, ?int $ignoreId = null): array
    {
        $documentRule = ['nullable', 'string', 'max:32'];
        if ($companyId) {
            $uniqueDocument = \Illuminate\Validation\Rule::unique('customers', 'document')
                ->where(fn ($query) => $query->where('company_id', $companyId));
            if ($ignoreId !== null) {
                $uniqueDocument->ignore($ignoreId);
            }
            $documentRule[] = $uniqueDocument;
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'document' => $documentRule,
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
