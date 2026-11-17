<?php

namespace App\Http\Controllers;

use App\Models\CompanyMembership;
use App\Models\ServiceCatalogItem;
use App\Services\AuditTrailService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ServiceCatalogController extends Controller
{
    public function index(Request $request, CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $search = trim((string) ($filters['q'] ?? ''));
        $items = ServiceCatalogItem::query()->when($search !== '', fn ($query) => $query->where(fn ($matching) => $matching->where('name', 'like', '%'.$search.'%')->orWhere('description', 'like', '%'.$search.'%')))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('name')->paginate(20)->withQueryString();
        $role = CompanyMembership::query()->where('company_id', $context->id())->where('user_id', $request->user()->id)->where('status', 'active')->value('role');

        return view('services.catalog', compact('items', 'filters', 'role'));
    }

    public function store(Request $request, CompanyContext $context, AuditTrailService $audit): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $this->authorizeAdmin($request, (int) $company->id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('service_catalog_items', 'name')->where('company_id', $company->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
        ]);
        $item = ServiceCatalogItem::create(['company_id' => $company->id, 'name' => trim($data['name']), 'description' => trim((string) ($data['description'] ?? '')) ?: null, 'price_cents' => $this->toCents($data['price']), 'status' => 'active']);
        $audit->record((int) $company->id, 'service.catalog_created', 'Serviço adicionado ao catálogo: '.$item->name, (int) $request->user()->id, 'service_catalog_item', (int) $item->id, ['price_cents' => (int) $item->price_cents]);

        return back()->with('service_notice', 'Serviço adicionado ao catálogo.');
    }

    public function update(Request $request, int $item, CompanyContext $context, AuditTrailService $audit): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $this->authorizeAdmin($request, (int) $company->id);
        $record = ServiceCatalogItem::query()->findOrFail($item);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('service_catalog_items', 'name')->where('company_id', $company->id)->ignore($record->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
        ]);
        $before = $record->only(['name', 'description', 'price_cents']);
        $record->update(['name' => trim($data['name']), 'description' => trim((string) ($data['description'] ?? '')) ?: null, 'price_cents' => $this->toCents($data['price'])]);
        $audit->record((int) $company->id, 'service.catalog_updated', 'Serviço atualizado no catálogo: '.$record->name, (int) $request->user()->id, 'service_catalog_item', (int) $record->id, ['before' => $before, 'after' => $record->only(['name', 'description', 'price_cents'])]);

        return back()->with('service_notice', 'Serviço atualizado.');
    }

    public function toggle(Request $request, int $item, CompanyContext $context, AuditTrailService $audit): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $this->authorizeAdmin($request, (int) $company->id);
        $record = ServiceCatalogItem::query()->findOrFail($item);
        $before = $record->status;
        $record->update(['status' => $record->status === 'active' ? 'inactive' : 'active']);
        $audit->record((int) $company->id, 'service.catalog_status_changed', 'Status do serviço alterado: '.$record->name, (int) $request->user()->id, 'service_catalog_item', (int) $record->id, ['before' => $before, 'after' => $record->status]);

        return back()->with('service_notice', 'Status do serviço atualizado.');
    }

    private function authorizeAdmin(Request $request, int $companyId): void
    {
        abort_unless(CompanyMembership::query()->where('company_id', $companyId)->where('user_id', $request->user()->id)->where('status', 'active')->where('role', 'Administrador')->exists(), 403);
    }

    private function toCents(mixed $value): int
    {
        return (int) round((float) str_replace(',', '.', (string) $value) * 100);
    }
}
