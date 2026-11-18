<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ServiceAsset;
use App\Models\ServiceWorkOrder;
use App\Models\ServiceWorkOrderItem;
use App\Services\AuditTrailService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ServiceWorkOrderEntryController extends Controller
{
    public function create(CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        return view('services.work-orders.create', [
            'customers' => Customer::query()->orderBy('name')->get(),
            'assets' => ServiceAsset::query()->where('status', 'active')->orderBy('name')->get(),
            'products' => Product::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, CompanyContext $context, AuditTrailService $audit): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $companyId = (int) $company->id;
        $data = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'service_asset_id' => ['nullable', 'integer', Rule::exists('service_assets', 'id')->where('company_id', $companyId)->where('status', 'active')],
            'asset_type' => ['nullable', Rule::in(['vehicle', 'motorcycle', 'equipment', 'other'])],
            'asset_description' => ['nullable', 'string', 'max:180'],
            'asset_identifier' => ['nullable', 'string', 'max:80'],
            'asset_manufacturer' => ['nullable', 'string', 'max:120'],
            'asset_model' => ['nullable', 'string', 'max:120'],
            'asset_year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 2)],
            'asset_color' => ['nullable', 'string', 'max:60'],
            'save_asset' => ['nullable', 'boolean'],
            'expected_at' => ['nullable', 'date', 'after_or_equal:today'],
            'warranty_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'mileage' => ['nullable', 'string', 'max:80'],
            'fuel_level' => ['nullable', 'string', 'max:80'],
            'complaint' => ['required', 'string', 'max:4000'],
            'diagnosis' => ['nullable', 'string', 'max:4000'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.type' => ['required', Rule::in(['product', 'service'])],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.name' => ['nullable', 'string', 'max:180'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'regex:/^\d+(?:[.,]\d{1,3})?$/'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:42949672.95', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
        ]);

        if (empty($data['service_asset_id']) && trim((string) ($data['asset_description'] ?? '')) === '') {
            throw ValidationException::withMessages(['service_asset_id' => 'Selecione um veículo/equipamento ou informe o item atendido.']);
        }
        foreach ($data['items'] ?? [] as $index => $line) {
            if ($line['type'] === 'product' && empty($line['product_id'])) {
                throw ValidationException::withMessages(['items.'.$index.'.product_id' => 'Selecione o produto ou peça.']);
            }
            if ($line['type'] === 'service' && trim((string) ($line['name'] ?? '')) === '') {
                throw ValidationException::withMessages(['items.'.$index.'.name' => 'Informe o serviço.']);
            }
            if ($line['type'] === 'service' && ! array_key_exists('unit_price', $line)) {
                throw ValidationException::withMessages(['items.'.$index.'.unit_price' => 'Informe o valor do serviço.']);
            }
        }

        $order = DB::transaction(function () use ($company, $companyId, $data, $request, $audit): ServiceWorkOrder {
            $customer = Customer::query()->whereKey($data['customer_id'])->lockForUpdate()->firstOrFail();
            $asset = null;
            if (! empty($data['service_asset_id'])) {
                $asset = ServiceAsset::query()->where('customer_id', $customer->id)->where('status', 'active')->whereKey($data['service_asset_id'])->lockForUpdate()->first();
                if (! $asset) throw ValidationException::withMessages(['service_asset_id' => 'Selecione um veículo/equipamento ativo deste cliente.']);
            } elseif (! empty($data['save_asset'])) {
                $asset = ServiceAsset::create([
                    'company_id' => $companyId, 'customer_id' => $customer->id,
                    'asset_type' => $data['asset_type'] ?? 'other',
                    'name' => trim((string) $data['asset_description']),
                    'manufacturer' => trim((string) ($data['asset_manufacturer'] ?? '')) ?: null,
                    'model' => trim((string) ($data['asset_model'] ?? '')) ?: null,
                    'year' => $data['asset_year'] ?? null, 'color' => trim((string) ($data['asset_color'] ?? '')) ?: null,
                    'identifier' => trim((string) ($data['asset_identifier'] ?? '')) ?: null, 'status' => 'active',
                ]);
            }

            $lines = [];
            foreach ($data['items'] ?? [] as $index => $line) {
                $quantity = (float) str_replace(',', '.', (string) $line['quantity']);
                $product = null;
                if ($line['type'] === 'product') {
                    $product = Product::query()->where('company_id', $companyId)->where('status', 'active')->whereKey($line['product_id'])->lockForUpdate()->first();
                    if (! $product) throw ValidationException::withMessages(['items.'.$index.'.product_id' => 'Produto não encontrado ou inativo. Atualize a tela e tente novamente.']);
                    $name = $product->name;
                    $unitCents = (int) $product->sale_price_cents;
                } else {
                    $name = trim((string) $line['name']);
                    $unitCents = (int) round((float) str_replace(',', '.', (string) ($line['unit_price'] ?? 0)) * 100);
                }
                $quantityMilli = (int) round($quantity * 1000);
                $lines[] = [
                    'company_id' => $companyId, 'item_type' => $line['type'], 'product_id' => $product?->id,
                    'name' => $name, 'description' => trim((string) ($line['description'] ?? '')) ?: null,
                    'quantity' => $quantity, 'unit_price_cents' => $unitCents,
                    'total_cents' => (int) round($quantityMilli * $unitCents / 1000),
                ];
            }

            $lockedCompany = Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();
            $lockedCompany->increment('last_work_order_number');
            $lockedCompany->refresh();
            $order = ServiceWorkOrder::create([
                'company_id' => $companyId, 'work_order_number' => $lockedCompany->last_work_order_number,
                'customer_id' => $customer->id, 'service_asset_id' => $asset?->id,
                'asset_type' => $asset?->asset_type ?? ($data['asset_type'] ?? null),
                'asset_description' => $asset?->name ?? (trim((string) ($data['asset_description'] ?? '')) ?: null),
                'asset_identifier' => $asset?->identifier ?? (trim((string) ($data['asset_identifier'] ?? '')) ?: null),
                'asset_manufacturer' => $asset?->manufacturer ?? ($data['asset_manufacturer'] ?? null),
                'asset_model' => $asset?->model ?? ($data['asset_model'] ?? null),
                'asset_year' => $asset?->year ?? ($data['asset_year'] ?? null),
                'asset_color' => $asset?->color ?? ($data['asset_color'] ?? null),
                'status' => 'open', 'complaint' => trim($data['complaint']),
                'diagnosis' => trim((string) ($data['diagnosis'] ?? '')) ?: null,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'warranty_days' => (int) ($data['warranty_days'] ?? 0),
                'opened_at' => now($company->timezone), 'expected_at' => $data['expected_at'] ?? null,
                'mileage' => trim((string) ($data['mileage'] ?? '')) ?: null,
                'fuel_level' => trim((string) ($data['fuel_level'] ?? '')) ?: null,
                'created_by' => $request->user()->id,
            ]);
            foreach ($lines as $line) {
                ServiceWorkOrderItem::create($line + ['service_work_order_id' => $order->id]);
            }
            $audit->record($companyId, 'service.work_order_created', 'OS #'.$order->work_order_number.' criada diretamente.', (int) $request->user()->id, 'service_work_order', (int) $order->id, ['customer_id' => (int) $customer->id, 'asset_id' => $asset?->id, 'item_count' => count($lines)]);
            return $order;
        });

        return redirect()->route('services.work-orders.show', $order->id)->with('work_order_notice', 'Ordem de serviço aberta.');
    }
}
