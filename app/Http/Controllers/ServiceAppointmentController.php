<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Company;
use App\Models\ServiceAppointment;
use App\Models\ServiceCatalogItem;
use App\Services\AuditTrailService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ServiceAppointmentController extends Controller
{
    public function index(Request $request, CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(['scheduled', 'confirmed', 'cancelled'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $timezone = $context->company()?->timezone ?? config('app.timezone');
        $pivot = now($timezone);
        $appointments = ServiceAppointment::query()->with(['customer', 'creator'])
            ->when(! empty($filters['from']), fn ($query) => $query->where('scheduled_at', '>=', Carbon::parse($filters['from'], $timezone)->startOfDay()))
            ->when(! empty($filters['to']), fn ($query) => $query->where('scheduled_at', '<=', Carbon::parse($filters['to'], $timezone)->endOfDay()))
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when($search !== '', fn ($query) => $query->where(fn ($matching) => $matching->where('service_name', 'like', '%'.$search.'%')->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$search.'%')->orWhere('phone', 'like', '%'.$search.'%'))))
            ->orderByRaw('CASE WHEN scheduled_at >= ? THEN 0 ELSE 1 END', [$pivot])
            ->orderByRaw('CASE WHEN scheduled_at >= ? THEN scheduled_at END ASC, CASE WHEN scheduled_at < ? THEN scheduled_at END DESC', [$pivot, $pivot])
            ->paginate(25)->withQueryString();

        return view('services.appointments.index', compact('appointments', 'filters', 'search', 'timezone'));
    }

    public function create(Request $request, CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $customerId = Customer::query()->where('status', 'active')->whereKey($request->query('customer_id'))->value('id');
        $serviceId = ServiceCatalogItem::query()->where('status', 'active')->whereKey($request->query('service_id'))->value('id');
        $timezone = $context->company()?->timezone ?? config('app.timezone');

        return view('services.appointments.create', [
            'customers' => Customer::query()->where('status', 'active')->orderBy('name')->get(),
            'services' => ServiceCatalogItem::query()->where('status', 'active')->orderBy('name')->get(),
            'customerId' => $customerId,
            'serviceId' => $serviceId,
            'timezone' => $timezone,
            'companyId' => (int) $context->id(),
            'requestKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, CompanyContext $context, AuditTrailService $audit): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $data = $this->validated($request, (int) $company->id, $company->timezone);

        DB::transaction(function () use ($company, $data, $request, $audit): void {
            Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            if (ServiceAppointment::query()->where('request_key', $data['request_key'])->exists()) return;
            $customer = Customer::query()->where('status', 'active')->whereKey($data['customer_id'])->lockForUpdate()->firstOrFail();
            $service = ServiceCatalogItem::query()->where('status', 'active')->whereKey($data['service_catalog_item_id'])->lockForUpdate()->firstOrFail();
            $record = ServiceAppointment::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'service_catalog_item_id' => $service->id,
                'service_name' => $service->name,
                'service_price_cents' => $service->price_cents,
                'scheduled_at' => Carbon::createFromFormat('Y-m-d\TH:i', $data['scheduled_at'], $company->timezone),
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'status' => 'scheduled',
                'request_key' => $data['request_key'],
                'created_by' => $request->user()->id,
            ]);
            $audit->record((int) $company->id, 'service.appointment_created', 'Agendamento criado para '.$customer->name.' em '.$record->scheduled_at->format('d/m/Y H:i').'.', (int) $request->user()->id, 'service_appointment', (int) $record->id, ['customer_id' => (int) $customer->id, 'service_catalog_item_id' => (int) $service->id, 'service_name' => $service->name, 'service_price_cents' => (int) $service->price_cents]);

        });

        return redirect()->route('services.appointments.index')->with('appointment_notice', 'Agendamento criado.');
    }

    public function update(Request $request, int $appointment, CompanyContext $context, AuditTrailService $audit): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $data = $request->validate(['scheduled_at' => ['required', 'date_format:Y-m-d\\TH:i'], 'notes' => ['nullable', 'string', 'max:4000']]);
        $scheduledAt = Carbon::createFromFormat('Y-m-d\\TH:i', $data['scheduled_at'], $company->timezone);
        if ($scheduledAt->lt(now($company->timezone))) throw ValidationException::withMessages(['scheduled_at' => 'Escolha uma data e hora futura.']);

        DB::transaction(function () use ($appointment, $company, $data, $scheduledAt, $request, $audit): void {
            $record = ServiceAppointment::query()->whereIn('status', ['scheduled', 'confirmed'])->whereKey($appointment)->lockForUpdate()->firstOrFail();
            $before = $record->only(['scheduled_at', 'notes']);
            $record->update([
                'scheduled_at' => $scheduledAt,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            ]);
            $audit->record((int) $company->id, 'service.appointment_updated', 'Agendamento atualizado #'.$record->id.'.', (int) $request->user()->id, 'service_appointment', (int) $record->id, ['before' => $before, 'after' => $record->only(['scheduled_at', 'notes'])]);
        });

        return back()->with('appointment_notice', 'Agendamento atualizado.');
    }

    public function status(Request $request, int $appointment, CompanyContext $context, AuditTrailService $audit): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $data = $request->validate(['status' => ['required', Rule::in(['scheduled', 'confirmed', 'cancelled'])]]);

        DB::transaction(function () use ($appointment, $company, $data, $request, $audit): void {
            $record = ServiceAppointment::query()->whereKey($appointment)->lockForUpdate()->firstOrFail();
            $allowed = match ($record->status) {
                'scheduled' => ['confirmed', 'cancelled'],
                'confirmed' => ['scheduled', 'cancelled'],
                default => [],
            };
            if (! in_array($data['status'], $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'Este agendamento não permite a mudança de situação solicitada.']);
            }
            $before = $record->status;
            $record->update(['status' => $data['status']]);
            $audit->record((int) $company->id, 'service.appointment_status_changed', 'Situação do agendamento #'.$record->id.' alterada.', (int) $request->user()->id, 'service_appointment', (int) $record->id, ['before' => $before, 'after' => $record->status]);
        });

        return back()->with('appointment_notice', 'Situação do agendamento atualizada.');
    }

    private function validated(Request $request, int $companyId, string $timezone): array
    {
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)->where('status', 'active')],
            'service_catalog_item_id' => ['required', 'integer', Rule::exists('service_catalog_items', 'id')->where('company_id', $companyId)->where('status', 'active')],
            'scheduled_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);
        $scheduledAt = Carbon::createFromFormat('Y-m-d\TH:i', $data['scheduled_at'], $timezone);
        if ($scheduledAt->lt(now($timezone))) throw ValidationException::withMessages(['scheduled_at' => 'Escolha uma data e hora futura.']);

        return $data;
    }
}
