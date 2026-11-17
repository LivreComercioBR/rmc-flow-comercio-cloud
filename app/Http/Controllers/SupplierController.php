<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class SupplierController extends Controller
{
    public function index(Request $request, CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = trim((string) ($filters['q'] ?? ''));
        $suppliers = Supplier::query()->when($search !== '', fn ($query) => $query->where(function ($matches) use ($search): void {
            $matches->where('name', 'like', '%'.$search.'%')->orWhere('document', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')->orWhere('contact_name', 'like', '%'.$search.'%');
        }))->orderBy('name')->paginate(20)->withQueryString();
        return view('suppliers.index', compact('suppliers', 'search'));
    }

    public function create(Request $request): View
    {
        $returnTo = $request->validate(['return_to' => ['nullable', Rule::in(['purchases.create', 'payables.manual.create'])]])['return_to'] ?? null;
        return view('suppliers.create', compact('returnTo'));
    }

    public function show(int $supplier, CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $record = Supplier::query()->findOrFail($supplier);
        $purchases = $record->purchases()->with('items')->latest('purchased_at')->paginate(15);
        $openPayables = $record->payables()->whereIn('status', ['pending', 'partial'])->orderBy('due_date')->get();
        $openPayableCents = $openPayables->sum(fn ($payable) => $payable->remainingCents());
        return view('suppliers.show', compact('record', 'purchases', 'openPayables', 'openPayableCents'));
    }

    public function store(Request $request, CompanyContext $context): RedirectResponse
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);
        $returnTo = $request->validate(['return_to' => ['nullable', Rule::in(['purchases.create', 'payables.manual.create'])]])['return_to'] ?? null;
        $supplier = Supplier::create($this->validated($request, $companyId) + ['company_id' => $companyId, 'status' => 'active']);
        if ($returnTo) return redirect()->route($returnTo, ['supplier_id' => $supplier->id]);
        return redirect()->route('suppliers.index')->with('supplier_notice', 'Fornecedor cadastrado.');
    }

    public function edit(int $supplier, CompanyContext $context): View
    {
        return view('suppliers.edit', ['supplier' => $this->findForCompany($supplier, $context), 'returnTo' => null]);
    }

    public function update(Request $request, int $supplier, CompanyContext $context): RedirectResponse
    {
        $record = $this->findForCompany($supplier, $context);
        $record->update($this->validated($request, $context->id(), $record->id));
        return redirect()->route('suppliers.index')->with('supplier_notice', 'Fornecedor atualizado.');
    }

    public function toggle(int $supplier, CompanyContext $context): RedirectResponse
    {
        $record = $this->findForCompany($supplier, $context);
        $record->update(['status' => $record->status === 'active' ? 'inactive' : 'active']);
        return redirect()->route('suppliers.index')->with('supplier_notice', 'Status do fornecedor atualizado.');
    }

    private function findForCompany(int $supplier, CompanyContext $context): Supplier
    {
        abort_unless($context->id(), 403);
        return Supplier::query()->findOrFail($supplier);
    }

    private function validated(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        $uniqueDocument = Rule::unique('suppliers', 'document')->where(fn ($query) => $query->where('company_id', $companyId));
        if ($ignoreId !== null) $uniqueDocument->ignore($ignoreId);
        return $request->validate([
            'name' => ['required', 'string', 'max:160'], 'document' => ['nullable', 'string', 'max:32', $uniqueDocument],
            'contact_name' => ['nullable', 'string', 'max:120'], 'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'], 'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
