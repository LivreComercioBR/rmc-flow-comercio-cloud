<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\Inventory\StockMovementService;
use App\Support\CompanyContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ProductController extends Controller
{
    public function index(Request $request, CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'low_stock'])],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $products = Product::query()->with('category')
            ->when($search !== '', fn ($query) => $query->where(function ($matches) use ($search): void {
                $matches->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('barcode', 'like', '%'.$search.'%');
            }))
            ->when(! empty($filters['category_id']), fn ($query) => $query->where('category_id', $filters['category_id']))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('status', 'active'))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('status', 'inactive'))
            ->when(($filters['status'] ?? null) === 'low_stock', fn ($query) => $query->where('status', 'active')->where('minimum_stock', '>', 0)->whereColumn('stock_quantity', '<=', 'minimum_stock'))
            ->orderBy('name')->paginate(20)->withQueryString();
        $categories = Category::query()->where('kind', 'product')->where('status', 'active')->orderBy('name')->get();
        return view('products.index', compact('products', 'categories', 'search', 'filters'));
    }

    public function create(): View
    {
        return view('products.create', ['categories' => $this->activeCategories()]);
    }

    public function store(Request $request, CompanyContext $context, StockMovementService $stock): RedirectResponse
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);
        $data = $this->validated($request, $companyId);
        $initialStock = (float) ($data['initial_stock'] ?? 0);
        unset($data['initial_stock'], $data['cost_price'], $data['sale_price'], $data['other_costs']);
        $data['cost_price_cents'] = $this->toCents($request->input('cost_price', '0'));
        $data['sale_price_cents'] = $this->toCents($request->input('sale_price', '0'));
        $data['other_costs_cents'] = $this->toCents($request->input('other_costs', '0'));
        $this->normalizePriceRates($data);

        try {
            $product = DB::transaction(function () use ($data, $companyId, $initialStock, $stock, $request): Product {
                $product = Product::create($data + [
                    'company_id' => $companyId,
                    'stock_quantity' => 0,
                    'status' => 'active',
                ]);
                if ($initialStock > 0) {
                    $stock->record($product, 'entry', $initialStock, 'Saldo inicial do cadastro', $request->user()->id);
                }
                return $product;
            });
        } catch (QueryException $exception) {
            return back()->withInput()->withErrors(['sku' => 'SKU ou c�digo de barras j� est� cadastrado nesta empresa.']);
        }

        return redirect()->route('products.index')->with('catalog_notice', 'Produto cadastrado.');
    }

    public function edit(int $product, CompanyContext $context): View
    {
        $record = $this->findForCompany($product, $context);
        return view('products.edit', ['product' => $record, 'categories' => $this->activeCategories($record->category)]);
    }

    public function update(Request $request, int $product, CompanyContext $context): RedirectResponse
    {
        $record = $this->findForCompany($product, $context);
        $data = $this->validated($request, $context->id(), $record->id, false);
        unset($data['cost_price'], $data['sale_price'], $data['other_costs']);
        $data['cost_price_cents'] = $this->toCents($request->input('cost_price', '0'));
        $data['sale_price_cents'] = $this->toCents($request->input('sale_price', '0'));
        $data['other_costs_cents'] = $this->toCents($request->input('other_costs', '0'));
        $this->normalizePriceRates($data);
        try {
            $record->update($data);
        } catch (QueryException $exception) {
            return back()->withInput()->withErrors(['sku' => 'SKU ou c�digo de barras j� est� cadastrado nesta empresa.']);
        }
        return redirect()->route('products.index')->with('catalog_notice', 'Produto atualizado.');
    }

    public function toggle(int $product, CompanyContext $context): RedirectResponse
    {
        $record = $this->findForCompany($product, $context);
        $record->update(['status' => $record->status === 'active' ? 'inactive' : 'active']);
        return redirect()->route('products.index')->with('catalog_notice', 'Status do produto atualizado.');
    }

    private function findForCompany(int $product, CompanyContext $context): Product
    {
        abort_unless($context->id(), 403);
        return Product::query()->findOrFail($product);
    }

    private function activeCategories(?Category $preserved = null)
    {
        $categories = Category::query()->where('kind', 'product')->where('status', 'active')->orderBy('name')->get();
        if ($preserved && $preserved->status !== 'active' && $preserved->kind === 'product') {
            $categories->push($preserved);
            return $categories->sortBy('name')->values();
        }
        return $categories;
    }

    private function validated(Request $request, int $companyId, ?int $ignoreId = null, bool $includeInitialStock = true): array
    {
        $skuRule = Rule::unique('products', 'sku')->where(fn ($query) => $query->where('company_id', $companyId));
        $barcodeRule = Rule::unique('products', 'barcode')->where(fn ($query) => $query->where('company_id', $companyId));
        if ($ignoreId !== null) {
            $skuRule->ignore($ignoreId);
            $barcodeRule->ignore($ignoreId);
        }
        $rules = [
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('kind', 'product')->where('status', 'active'))],
            'name' => ['required', 'string', 'max:180'],
            'sku' => ['nullable', 'string', 'max:64', $skuRule],
            'barcode' => ['nullable', 'string', 'max:64', $barcodeRule],
            'unit' => ['required', 'string', 'max:12'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:42949672.95', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:42949672.95', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
            'icms_rate' => ['nullable', 'numeric', 'min:0', 'max:999999.9999'],
            'ipi_rate' => ['nullable', 'numeric', 'min:0', 'max:999999.9999'],
            'import_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:999999.9999'],
            'export_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:999999.9999'],
            'iss_rate' => ['nullable', 'numeric', 'min:0', 'max:999999.9999'],
            'operational_cost_rate' => ['nullable', 'numeric', 'min:0', 'max:999999.9999'],
            'profit_rate' => ['nullable', 'numeric', 'min:0', 'max:999999.9999'],
            'other_costs' => ['nullable', 'numeric', 'min:0', 'max:42949672.95', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
            'minimum_stock' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'regex:/^\d+(?:[.,]\d{1,3})?$/'],
            'shelf' => ['nullable', 'string', 'max:80'],
        ];
        if ($includeInitialStock) {
            $rules['initial_stock'] = ['nullable', 'numeric', 'min:0', 'max:999999999.999', 'regex:/^\d+(?:[.,]\d{1,3})?$/'];
        }
        return $request->validate($rules);
    }

    private function toCents(mixed $value): int
    {
        return (int) round((float) str_replace(',', '.', (string) ($value ?: '0')) * 100);
    }

    private function normalizePriceRates(array &$data): void
    {
        foreach (['icms_rate', 'ipi_rate', 'import_tax_rate', 'export_tax_rate', 'iss_rate', 'operational_cost_rate', 'profit_rate'] as $field) {
            $data[$field] = $data[$field] ?? 0;
        }
    }
}
