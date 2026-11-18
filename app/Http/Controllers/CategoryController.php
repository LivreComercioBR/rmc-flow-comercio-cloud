<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CategoryController extends Controller
{
    private const KINDS = ['product', 'service', 'revenue', 'expense'];

    public function index(CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $categories = Category::query()->withCount('products')->orderBy('kind')->orderBy('name')->get();
        return view('categories.index', compact('categories'));
    }

    public function store(Request $request, CompanyContext $context): RedirectResponse
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);
        Category::create($this->validated($request, $companyId) + ['company_id' => $companyId, 'status' => 'active']);
        return redirect()->route('categories.index')->with('catalog_notice', 'Categoria cadastrada.');
    }

    public function update(Request $request, int $category, CompanyContext $context): RedirectResponse
    {
        $record = $this->findForCompany($category, $context);
        $record->update($this->validated($request, $context->id(), $record->id));
        return redirect()->route('categories.index')->with('catalog_notice', 'Categoria atualizada.');
    }

    public function toggle(int $category, CompanyContext $context): RedirectResponse
    {
        $record = $this->findForCompany($category, $context);
        $record->update(['status' => $record->status === 'active' ? 'inactive' : 'active']);
        return redirect()->route('categories.index')->with('catalog_notice', 'Status da categoria atualizado.');
    }

    public function destroy(int $category, CompanyContext $context): RedirectResponse
    {
        $record = $this->findForCompany($category, $context);
        $isReferenced = $record->products()->exists()
            || AccountPayable::query()->where('financial_category_id', $record->id)->exists()
            || AccountReceivable::query()->where('financial_category_id', $record->id)->exists();

        if ($isReferenced) {
            return redirect()->route('categories.index')->with('catalog_error', 'Esta categoria j� est� vinculada a produtos ou lan�amentos financeiros. Desative-a para preservar o hist�rico.');
        }

        $record->delete();
        return redirect()->route('categories.index')->with('catalog_notice', 'Categoria exclu�da.');
    }

    private function findForCompany(int $category, CompanyContext $context): Category
    {
        abort_unless($context->id(), 403);
        return Category::query()->findOrFail($category);
    }

    private function validated(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        $kind = $request->input('kind');
        $kind = is_string($kind) && in_array($kind, self::KINDS, true) ? $kind : '';
        $uniqueName = Rule::unique('categories', 'name')->where(fn ($query) => $query->where('company_id', $companyId)->where('kind', $kind));
        if ($ignoreId !== null) $uniqueName->ignore($ignoreId);

        return $request->validate([
            'name' => ['required', 'string', 'max:120', $uniqueName],
            'kind' => ['required', 'string', Rule::in(self::KINDS)],
        ]);
    }
}
