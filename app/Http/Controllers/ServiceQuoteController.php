<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ServiceAppointment;
use App\Models\ServiceCatalogItem;
use App\Models\ServiceQuote;
use App\Models\ServiceQuoteItem;
use App\Models\ServiceAsset;
use App\Services\AuditTrailService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ServiceQuoteController extends Controller
{
    public function index(Request $request, CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['draft', 'sent', 'approved', 'rejected'])],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $timezone = $context->company()?->timezone ?? config('app.timezone');
        $quotes = ServiceQuote::query()->with('customer')
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when($search !== '', fn ($query) => $query->where(fn ($matching) => $matching->where('quote_number', 'like', '%'.$search.'%')->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$search.'%')->orWhere('document', 'like', '%'.$search.'%'))))
            ->orderByDesc('quote_number')->paginate(20)->withQueryString();

        return view('services.quotes.index', compact('quotes', 'filters', 'search', 'timezone'));
    }

    public function create(Request $request, CompanyContext $context): View
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);
        $appointment = null;
        if ($request->filled('appointment_id')) {
            $appointment = ServiceAppointment::query()->with('customer')->whereIn('status', ['scheduled', 'confirmed'])->findOrFail($request->integer('appointment_id'));
        }
        $customerId = Customer::query()->where('status', 'active')->whereKey($request->query('customer_id', $appointment?->customer_id))->value('id');
        $customers = Customer::query()->where('status', 'active')->orderBy('name')->get();
        if ($appointment && ! $customers->contains('id', $appointment->customer_id)) $customers->push($appointment->customer);
        $seedItems = $appointment ? [[
            'item_type' => 'manual',
            'name' => $appointment->service_name,
            'description' => 'Serviço previsto no agendamento #'.$appointment->id,
            'quantity' => '1.000',
            'unit_price' => number_format($appointment->service_price_cents / 100, 2, '.', ''),
        ]] : [['item_type' => 'service', 'source_id' => '', 'name' => '', 'description' => '', 'quantity' => '1.000', 'unit_price' => '0.00']];

        return view('services.quotes.create', [
            'customers' => $customers,
            'services' => ServiceCatalogItem::query()->where('status', 'active')->orderBy('name')->get(),
            'products' => Product::query()->where('status', 'active')->orderBy('name')->get(),
            'appointments' => ServiceAppointment::query()->with('customer')->whereIn('status', ['scheduled', 'confirmed'])->orderBy('scheduled_at')->limit(100)->get(),
            'appointment' => $appointment,
            'customerId' => $customerId ?? $appointment?->customer_id,
            'companyId' => (int) $companyId,
            'expiresAt' => now($context->company()?->timezone ?? config('app.timezone'))->addDays(10)->toDateString(),
            'requestKey' => (string) Str::uuid(),
            'seedItems' => $seedItems,
        ]);
    }

    public function edit(int $quote, CompanyContext $context): View
    {
        $company = $context->company();
        abort_unless($company, 403);
        $record = ServiceQuote::query()->with(['customer', 'appointment'])->where('status', 'draft')->findOrFail($quote);
        $seedItems = $record->items()->get()->map(fn (ServiceQuoteItem $item) => [
            'item_type' => $item->item_type,
            'source_id' => $item->service_catalog_item_id ?? $item->product_id,
            'name' => $item->name,
            'description' => $item->description,
            'quantity' => number_format((float) $item->quantity, 3, '.', ''),
            'unit_price' => number_format($item->unit_price_cents / 100, 2, '.', ''),
        ])->all();
        $customers = Customer::query()->where('status', 'active')->orderBy('name')->get();
        if (! $customers->contains('id', $record->customer_id)) $customers->push($record->customer);

        return view('services.quotes.create', [
            'customers' => $customers,
            'services' => ServiceCatalogItem::query()->where('status', 'active')->orderBy('name')->get(),
            'products' => Product::query()->where('status', 'active')->orderBy('name')->get(),
            'appointments' => ServiceAppointment::query()->with('customer')->whereIn('status', ['scheduled', 'confirmed'])->orderBy('scheduled_at')->limit(100)->get(),
            'appointment' => $record->appointment,
            'customerId' => $record->customer_id,
            'expiresAt' => $record->expires_at->toDateString(),
            'requestKey' => $record->request_key,
            'seedItems' => $seedItems,
            'quote' => $record,
            'companyId' => (int) $context->id(),
        ]);
    }

    public function store(Request $request, CompanyContext $context, AuditTrailService $audit): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $data = $this->validated($request, (int) $company->id, $company->timezone);

        $quote = DB::transaction(function () use ($company, $data, $request, $audit): ServiceQuote {
            $lockedCompany = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $existing = ServiceQuote::query()->where('request_key', $data['request_key'])->first();
            if ($existing) return $existing;
            $customer = Customer::query()->whereKey($data['customer_id'])->lockForUpdate()->firstOrFail();
            $appointment = null;
            if (! empty($data['service_appointment_id'])) {
                $appointment = ServiceAppointment::query()->whereIn('status', ['scheduled', 'confirmed'])->whereKey($data['service_appointment_id'])->lockForUpdate()->firstOrFail();
                if ((int) $appointment->customer_id !== (int) $customer->id) throw ValidationException::withMessages(['service_appointment_id' => 'O agendamento selecionado pertence a outro cliente.']);
            }

            $normalizedItems = $this->normalizeItems($data['items'], (int) $company->id);
            $subtotal = collect($normalizedItems)->sum('total_cents');
            if ($subtotal <= 0) throw ValidationException::withMessages(['items' => 'O orçamento precisa ter valor total maior que zero.']);

            $lockedCompany->increment('last_quote_number');
            $lockedCompany->refresh();
            $record = ServiceQuote::create([
                'company_id' => $company->id,
                'quote_number' => $lockedCompany->last_quote_number,
                'customer_id' => $customer->id,
                'service_appointment_id' => $appointment?->id,
                'subtotal_cents' => $subtotal,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'expires_at' => $data['expires_at'],
                'status' => 'draft',
                'request_key' => $data['request_key'],
                'created_by' => $request->user()->id,
            ]);
            foreach ($normalizedItems as $item) ServiceQuoteItem::create($item + ['company_id' => $company->id, 'service_quote_id' => $record->id]);
            $audit->record((int) $company->id, 'service.quote_created', 'Orçamento #'.$record->quote_number.' criado para '.$customer->name.'.', (int) $request->user()->id, 'service_quote', (int) $record->id, ['customer_id' => (int) $customer->id, 'subtotal_cents' => $subtotal, 'service_appointment_id' => $appointment?->id, 'item_count' => count($normalizedItems)]);

            return $record;
        });

        return redirect()->route('services.quotes.show', $quote->id)->with('quote_notice', 'Orçamento salvo como rascunho.');
    }

    public function update(Request $request, int $quote, CompanyContext $context, AuditTrailService $audit): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $data = $this->validated($request, (int) $company->id, $company->timezone);
        DB::transaction(function () use ($quote, $company, $data, $request, $audit): void {
            Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $record = ServiceQuote::query()->where('status', 'draft')->whereKey($quote)->lockForUpdate()->firstOrFail();
            $customer = Customer::query()->whereKey($data['customer_id'])->lockForUpdate()->firstOrFail();
            $appointment = null;
            if (! empty($data['service_appointment_id'])) {
                $appointmentQuery = ServiceAppointment::query()->whereKey($data['service_appointment_id']);
                if ((int) $data['service_appointment_id'] !== (int) $record->service_appointment_id) $appointmentQuery->whereIn('status', ['scheduled', 'confirmed']);
                $appointment = $appointmentQuery->lockForUpdate()->firstOrFail();
                if ((int) $appointment->customer_id !== (int) $customer->id) throw ValidationException::withMessages(['service_appointment_id' => 'O agendamento selecionado pertence a outro cliente.']);
            }
            $normalizedItems = $this->normalizeItems($data['items'], (int) $company->id);
            $subtotal = collect($normalizedItems)->sum('total_cents');
            if ($subtotal <= 0) throw ValidationException::withMessages(['items' => 'O orçamento precisa ter valor total maior que zero.']);
            $before = [
                'customer_id' => (int) $record->customer_id,
                'subtotal_cents' => (int) $record->subtotal_cents,
                'expires_at' => $record->expires_at->toDateString(),
                'items' => $record->items()->get(['item_type', 'name', 'quantity', 'unit_price_cents', 'total_cents'])->toArray(),
            ];
            $record->update([
                'customer_id' => $customer->id,
                'service_appointment_id' => $appointment?->id,
                'subtotal_cents' => $subtotal,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'expires_at' => $data['expires_at'],
            ]);
            $record->items()->delete();
            foreach ($normalizedItems as $item) ServiceQuoteItem::create($item + ['company_id' => $company->id, 'service_quote_id' => $record->id]);
            $audit->record((int) $company->id, 'service.quote_updated', 'Rascunho do orçamento #'.$record->quote_number.' atualizado.', (int) $request->user()->id, 'service_quote', (int) $record->id, ['before' => $before, 'after' => ['customer_id' => (int) $record->customer_id, 'subtotal_cents' => $subtotal, 'expires_at' => $record->expires_at->toDateString(), 'items' => $normalizedItems]]);
        });

        return redirect()->route('services.quotes.show', $quote)->with('quote_notice', 'Rascunho do orçamento atualizado.');
    }

    public function show(int $quote, CompanyContext $context): View
    {
        $company = $context->company();
        abort_unless($company, 403);
        $record = ServiceQuote::query()->with(['customer', 'appointment', 'items', 'creator', 'decisionMaker', 'workOrder'])->findOrFail($quote);
        $settings = $company->settings()->whereIn('setting_key', ['company.address'])->pluck('setting_value', 'setting_key');
        $address = $settings->get('company.address') ?? [];
        $addressText = implode(', ', array_filter([$address['street'] ?? null, $address['number'] ?? null, $address['district'] ?? null, $address['city'] ?? null, $address['state'] ?? null, $address['postal_code'] ?? null]));
        $whatsappText = "Orçamento #{$record->quote_number} - {$company->trade_name}\nCliente: {$record->customer->name}\nValidade: {$record->expires_at->format('d/m/Y')}\n";
        foreach ($record->items as $item) $whatsappText .= "\n{$item->name} | ".number_format((float) $item->quantity, 3, ',', '.')." x R$ ".number_format($item->unit_price_cents / 100, 2, ',', '.')." = R$ ".number_format($item->total_cents / 100, 2, ',', '.');
        $whatsappText .= "\n\nTotal: R$ ".number_format($record->subtotal_cents / 100, 2, ',', '.');
        if ($record->notes) $whatsappText .= "\nObservações: {$record->notes}";
        $phone = preg_replace('/\D+/', '', (string) $record->customer->phone);
        if (strlen($phone) === 10 || strlen($phone) === 11) $phone = '55'.$phone;
        if (! in_array(strlen($phone), [12, 13], true)) $phone = '';
        $whatsappUrl = 'https://wa.me/'.($phone ?: '').'?text='.rawurlencode($whatsappText);
        $expired = $record->expires_at->lt(now($company->timezone)->startOfDay());

        $assets = ServiceAsset::query()->where('customer_id', $record->customer_id)->where('status', 'active')->orderBy('name')->get();
        return view('services.quotes.show', ['quote' => $record, 'company' => $company, 'addressText' => $addressText, 'whatsappUrl' => $whatsappUrl, 'expired' => $expired, 'assets' => $assets]);
    }

    public function markSent(int $quote, CompanyContext $context, AuditTrailService $audit): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        DB::transaction(function () use ($quote, $company, $audit): void {
            $record = ServiceQuote::query()->whereKey($quote)->lockForUpdate()->firstOrFail();
            if ($record->status !== 'draft' || $record->expires_at->lt(now($company->timezone)->startOfDay())) throw ValidationException::withMessages(['quote' => 'Este orçamento não pode ser marcado como enviado. Confira a situação e a validade.']);
            $record->update(['status' => 'sent', 'sent_at' => now($company->timezone)]);
            $audit->record((int) $company->id, 'service.quote_sent', 'Orçamento #'.$record->quote_number.' marcado como enviado.', (int) request()->user()->id, 'service_quote', (int) $record->id);
        });

        return back()->with('quote_notice', 'Orçamento marcado como enviado.');
    }

    public function decide(Request $request, int $quote, CompanyContext $context, AuditTrailService $audit): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'decision_contact_name' => ['required', 'string', 'max:160'],
            'decision_channel' => ['required', Rule::in(['in_person', 'phone', 'whatsapp', 'email'])],
            'decision_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($quote, $company, $data, $request, $audit): void {
            $record = ServiceQuote::query()->whereKey($quote)->lockForUpdate()->firstOrFail();
            if ($record->status !== 'sent' || $record->expires_at->lt(now($company->timezone)->startOfDay())) throw ValidationException::withMessages(['quote' => 'Somente orçamento enviado e dentro da validade pode receber uma decisão.']);
            $record->update([
                'status' => $data['status'],
                'decision_at' => now($company->timezone),
                'decision_by' => $request->user()->id,
                'decision_contact_name' => trim($data['decision_contact_name']),
                'decision_channel' => $data['decision_channel'],
                'decision_notes' => trim((string) ($data['decision_notes'] ?? '')) ?: null,
            ]);
            $audit->record((int) $company->id, 'service.quote_decided', 'Orçamento #'.$record->quote_number.' '.($record->status === 'approved' ? 'aprovado' : 'reprovado').'.', (int) $request->user()->id, 'service_quote', (int) $record->id, ['status' => $record->status, 'contact_name' => $record->decision_contact_name, 'channel' => $record->decision_channel]);
        });

        return back()->with('quote_notice', 'Decisão do orçamento registrada.');
    }

    private function validated(Request $request, int $companyId, string $timezone): array
    {
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'service_appointment_id' => ['nullable', 'integer', Rule::exists('service_appointments', 'id')->where('company_id', $companyId)],
            'expires_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.item_type' => ['required', Rule::in(['service', 'product', 'manual'])],
            'items.*.source_id' => ['nullable', 'integer'],
            'items.*.name' => ['nullable', 'string', 'max:180'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000', 'regex:/^\d+(?:[.,]\d{1,3})?$/'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
        ]);
        $expires = Carbon::parse($data['expires_at'], $timezone)->startOfDay();
        if ($expires->lt(now($timezone)->startOfDay())) throw ValidationException::withMessages(['expires_at' => 'A validade precisa ser hoje ou uma data futura.']);
        foreach ($data['items'] as $index => $item) {
            if ($item['item_type'] === 'manual') {
                Validator::make($item, ['name' => ['required', 'string', 'max:180'], 'unit_price' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'regex:/^\d+(?:[.,]\d{1,2})?$/']])->validate();
            } else {
                Validator::make($item, ['source_id' => ['required', 'integer']])->validate();
            }
        }

        return $data;
    }

    private function normalizeItems(array $items, int $companyId): array
    {
        $normalized = [];
        foreach ($items as $item) {
            $serviceId = null;
            $productId = null;
            if ($item['item_type'] === 'service') {
                $source = ServiceCatalogItem::query()->where('status', 'active')->whereKey($item['source_id'])->lockForUpdate()->firstOrFail();
                $serviceId = $source->id;
                $name = $source->name;
                $description = $source->description;
                $unitCents = (int) $source->price_cents;
            } elseif ($item['item_type'] === 'product') {
                $source = Product::query()->where('status', 'active')->whereKey($item['source_id'])->lockForUpdate()->firstOrFail();
                $productId = $source->id;
                $name = $source->name;
                $description = null;
                $unitCents = (int) $source->sale_price_cents;
            } else {
                $name = trim($item['name']);
                $description = trim((string) ($item['description'] ?? '')) ?: null;
                $unitCents = (int) round((float) str_replace(',', '.', $item['unit_price']) * 100);
            }
            $quantity = (float) str_replace(',', '.', $item['quantity']);
            $lineCents = (int) round($quantity * $unitCents);
            $normalized[] = [
                'item_type' => $item['item_type'],
                'service_catalog_item_id' => $serviceId,
                'product_id' => $productId,
                'name' => $name,
                'description' => $description,
                'quantity' => number_format($quantity, 3, '.', ''),
                'unit_price_cents' => $unitCents,
                'total_cents' => $lineCents,
            ];
        }

        return $normalized;
    }
}
