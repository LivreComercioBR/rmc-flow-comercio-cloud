<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ServiceAsset;
use App\Services\AuditTrailService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ServiceAssetController extends Controller
{
    public function index(Request $request, CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $search = trim((string) ($filters['q'] ?? ''));
        $assets = ServiceAsset::query()->with(['customer', 'workOrders' => fn ($query) => $query->orderByDesc('opened_at')->limit(5)])->withCount('workOrders')
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when($search !== '', fn ($q) => $q->where(fn ($match) => $match->where('name', 'like', '%'.$search.'%')->orWhere('asset_type', 'like', '%'.$search.'%')->orWhere('manufacturer', 'like', '%'.$search.'%')->orWhere('model', 'like', '%'.$search.'%')->orWhere('identifier', 'like', '%'.$search.'%')->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$search.'%')->orWhere('document', 'like', '%'.$search.'%'))))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('services.assets.index', compact('assets', 'filters', 'search'));
    }

    public function show(int $asset, CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $record = ServiceAsset::query()->with('customer')->findOrFail($asset);
        $workOrders = $record->workOrders()->paginate(20);

        return view('services.assets.show', compact('record', 'workOrders'));
    }

    public function store(Request $request, int $customer, CompanyContext $context, AuditTrailService $audit): RedirectResponse
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);
        $record = Customer::query()->whereKey($customer)->firstOrFail();
        $data = $request->validate([
            'asset_type' => ['required', Rule::in(['vehicle', 'motorcycle', 'equipment', 'other'])], 'name' => ['required', 'string', 'max:180'],
            'manufacturer' => ['nullable', 'string', 'max:120'], 'model' => ['nullable', 'string', 'max:120'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 2)],
            'color' => ['nullable', 'string', 'max:60'], 'identifier' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($data, $companyId, $record, $audit, $request): void {
            $asset = ServiceAsset::create($data + ['company_id' => $companyId, 'customer_id' => $record->id, 'status' => 'active']);
            $audit->record((int) $companyId, 'service.asset_created', 'Veículo/equipamento cadastrado para '.$record->name.'.', (int) $request->user()->id, 'service_asset', (int) $asset->id, ['customer_id' => (int) $record->id, 'asset_type' => $asset->asset_type, 'identifier' => $asset->identifier]);
        });

        return redirect()->route('customers.show', $record->id)->with('asset_notice', 'Veículo/equipamento cadastrado no histórico do cliente.');
    }

    public function update(Request $request, int $customer, int $asset, CompanyContext $context, AuditTrailService $audit): RedirectResponse
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);
        $customerRecord = Customer::query()->whereKey($customer)->firstOrFail();
        $record = ServiceAsset::query()->where('customer_id', $customerRecord->id)->whereKey($asset)->firstOrFail();
        $data = $request->validate([
            'asset_type' => ['required', Rule::in(['vehicle', 'motorcycle', 'equipment', 'other'])], 'name' => ['required', 'string', 'max:180'],
            'manufacturer' => ['nullable', 'string', 'max:120'], 'model' => ['nullable', 'string', 'max:120'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 2)],
            'color' => ['nullable', 'string', 'max:60'], 'identifier' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $before = $record->only(['asset_type', 'name', 'manufacturer', 'model', 'year', 'color', 'identifier', 'notes']);
        DB::transaction(function () use ($record, $data, $audit, $companyId, $customerRecord, $request, $before): void {
            $record->update($data);
            $audit->record((int) $companyId, 'service.asset_updated', 'Veículo/equipamento atualizado para '.$customerRecord->name.'.', (int) $request->user()->id, 'service_asset', (int) $record->id, ['before' => $before, 'after' => $record->only(array_keys($before))]);
        });

        return redirect()->route('services.assets.show', $record->id)->with('asset_notice', 'Cadastro atualizado. As OS existentes mantêm os dados registrados na abertura.');
    }

    public function status(Request $request, int $customer, int $asset, CompanyContext $context, AuditTrailService $audit): RedirectResponse
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);
        $customerRecord = Customer::query()->whereKey($customer)->firstOrFail();
        $record = ServiceAsset::query()->where('customer_id', $customerRecord->id)->whereKey($asset)->firstOrFail();
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]]);
        DB::transaction(function () use ($record, $data, $audit, $companyId, $customerRecord, $request): void {
            $before = $record->status;
            $record->update(['status' => $data['status']]);
            $audit->record((int) $companyId, 'service.asset_status_changed', 'Situação do veículo/equipamento alterada para '.$customerRecord->name.'.', (int) $request->user()->id, 'service_asset', (int) $record->id, ['before' => $before, 'after' => $record->status]);
        });

        return redirect()->route('customers.show', $customerRecord->id)->with('asset_notice', 'Situação do veículo/equipamento atualizada.');
    }
}
