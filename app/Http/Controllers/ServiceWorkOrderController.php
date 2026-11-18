<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\AccountReceivable;
use App\Models\AccountReceivablePayment;
use App\Models\CashSession;
use App\Models\CashMovement;
use App\Models\CompanyMembership;
use App\Models\Product;
use App\Models\ServiceAppointment;
use App\Models\ServiceCatalogItem;
use App\Models\ServiceAsset;
use App\Models\ServiceQuote;
use App\Models\ServiceWorkOrder;
use App\Models\ServiceWorkOrderItem;
use App\Models\ServiceWorkOrderRefund;
use App\Models\User;
use App\Services\Cash\CashRegisterService;
use App\Services\AuditTrailService;
use App\Services\Inventory\StockMovementService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ServiceWorkOrderController extends Controller
{
    private const STATUSES = [
        'open' => 'Aberta', 'diagnosis' => 'Em diagnóstico', 'awaiting_quote' => 'Aguardando orçamento', 'quote' => 'Orçamento',
        'awaiting_approval' => 'Aguardando aprovação', 'approved' => 'Aprovado', 'in_maintenance' => 'Em manutenção',
        'awaiting_part' => 'Aguardando peça', 'in_progress' => 'Em execução', 'completed' => 'Serviço concluído', 'awaiting_payment' => 'Aguardando pagamento',
        'ready' => 'Pronta para retirada', 'finalized' => 'Finalizada', 'cancelled' => 'Cancelada',
    ];

    public function index(Request $request, CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))], 'q' => ['nullable', 'string', 'max:100'],
            'warranty' => ['nullable', Rule::in(['active', 'expired'])],
            'warranty_from' => ['nullable', 'date_format:Y-m-d'], 'warranty_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:warranty_from'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $today = now($context->company()?->timezone ?? config('app.timezone'))->toDateString();
        $orders = ServiceWorkOrder::query()->with('customer')
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('work_order_number', 'like', '%'.$search.'%')->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$search.'%')->orWhere('document', 'like', '%'.$search.'%'))))
            ->when(! empty($filters['warranty']) || ! empty($filters['warranty_from']) || ! empty($filters['warranty_to']), function ($q) use ($filters, $today): void {
                $q->where('status', 'finalized')->whereNotNull('finalized_at')->where('warranty_days', '>', 0);
                if (($filters['warranty'] ?? null) === 'active') $q->whereRaw('DATE(DATE_ADD(finalized_at, INTERVAL warranty_days DAY)) >= ?', [$today]);
                if (($filters['warranty'] ?? null) === 'expired') $q->whereRaw('DATE(DATE_ADD(finalized_at, INTERVAL warranty_days DAY)) < ?', [$today]);
                if (! empty($filters['warranty_from'])) $q->whereRaw('DATE(DATE_ADD(finalized_at, INTERVAL warranty_days DAY)) >= ?', [$filters['warranty_from']]);
                if (! empty($filters['warranty_to'])) $q->whereRaw('DATE(DATE_ADD(finalized_at, INTERVAL warranty_days DAY)) <= ?', [$filters['warranty_to']]);
            })
            ->orderByDesc('work_order_number')->paginate(20)->withQueryString();

        return view('services.work-orders.index', ['orders' => $orders, 'filters' => $filters, 'search' => $search, 'statuses' => self::STATUSES, 'today' => $today]);
    }

    public function show(int $workOrder, CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $order = ServiceWorkOrder::query()->with(['customer', 'quote', 'appointment', 'asset', 'items.product', 'creator'])->findOrFail($workOrder);
        $next = $order->stock_applied_at
            ? match ($order->status) {
                'completed' => ['awaiting_payment'], 'awaiting_payment' => ['ready'], 'ready' => ['finalized'], default => [],
            }
            : array_values(array_diff(['open', 'diagnosis', 'awaiting_quote', 'quote', 'awaiting_approval', 'approved', 'in_maintenance', 'awaiting_part', 'in_progress', 'completed', 'awaiting_payment'], [$order->status]));
        $order->load(['receivable.payments.creator', 'refunds.creator', 'refunds.approver', 'cancelledBy', 'cancellationApprover']);
        $cashSessionOpen = CashSession::query()->where('status', 'open')->exists();
        $role = CompanyMembership::query()->where('company_id', $context->id())->where('user_id', auth()->id())->where('status', 'active')->value('role');
        $receivedByMethod = AccountReceivablePayment::query()->whereHas('receivable', fn ($query) => $query->where('service_work_order_id', $order->id))
            ->selectRaw('payment_method, SUM(amount_cents) as total_cents')->groupBy('payment_method')->pluck('total_cents', 'payment_method');
        $refundedByMethod = ServiceWorkOrderRefund::query()->where('service_work_order_id', $order->id)
            ->selectRaw('payment_method, SUM(amount_cents) as total_cents')->groupBy('payment_method')->pluck('total_cents', 'payment_method');
        $methodLabels = ['cash' => 'Dinheiro', 'pix' => 'PIX', 'debit_card' => 'Débito', 'credit_card' => 'Crédito', 'bank_transfer' => 'Transferência', 'other' => 'Outro'];
        $refundOptions = collect($methodLabels)->map(function (string $label, string $method) use ($receivedByMethod, $refundedByMethod): array {
            $remaining = max(0, (int) $receivedByMethod->get($method, 0) - (int) $refundedByMethod->get($method, 0));
            return ['label' => $label, 'remaining_cents' => $remaining];
        })->filter(fn (array $option) => $option['remaining_cents'] > 0);
        $defaultRefundMethod = $refundOptions->keys()->first();
        if (! $cashSessionOpen && $defaultRefundMethod === 'cash') $defaultRefundMethod = $refundOptions->keys()->skip(1)->first();

        return view('services.work-orders.show', ['order' => $order, 'products' => Product::query()->where('company_id', $context->id())->where('status', 'active')->orderBy('name')->get(), 'serviceCatalog' => ServiceCatalogItem::query()->where('company_id', $context->id())->where('status', 'active')->orderBy('name')->get(), 'statuses' => self::STATUSES, 'nextStatuses' => $next, 'cashSessionOpen' => $cashSessionOpen, 'role' => $role, 'canRequestCancellation' => in_array($role, ['Administrador', 'Atendente'], true), 'refundOptions' => $refundOptions, 'defaultRefundMethod' => $defaultRefundMethod, 'refundRequestKey' => (string) Str::uuid()]);
    }

    public function createFromQuote(Request $request, int $quote, CompanyContext $context, AuditTrailService $audit): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $data = $request->validate([
            'service_asset_id' => ['nullable', 'integer', Rule::exists('service_assets', 'id')->where('company_id', $company->id)->where('status', 'active')],
            'asset_type' => ['nullable', Rule::in(['vehicle', 'motorcycle', 'equipment', 'other'])], 'asset_description' => ['nullable', 'string', 'max:180'], 'asset_identifier' => ['nullable', 'string', 'max:80'],
            'asset_manufacturer' => ['nullable', 'string', 'max:120'], 'asset_model' => ['nullable', 'string', 'max:120'], 'asset_year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 2)], 'asset_color' => ['nullable', 'string', 'max:60'],
            'save_asset' => ['nullable', 'boolean'], 'complaint' => ['nullable', 'string', 'max:4000'], 'notes' => ['nullable', 'string', 'max:4000'],
        ]);
        if (! empty($data['save_asset']) && empty($data['service_asset_id']) && empty(trim((string) ($data['asset_description'] ?? '')))) {
            throw ValidationException::withMessages(['asset_description' => 'Informe o nome do veículo/equipamento para salvá-lo no cadastro do cliente.']);
        }

        $order = DB::transaction(function () use ($company, $quote, $data, $request, $audit): ServiceWorkOrder {
            $lockedCompany = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $existing = ServiceWorkOrder::query()->where('service_quote_id', $quote)->first();
            if ($existing) return $existing;
            $approved = ServiceQuote::query()->with('items')->whereKey($quote)->lockForUpdate()->firstOrFail();
            if ($approved->status !== 'approved' || $approved->expires_at->lt(now($company->timezone)->startOfDay())) {
                throw ValidationException::withMessages(['quote' => 'Somente um orçamento aprovado e dentro da validade pode originar uma ordem de serviço.']);
            }
            if ($approved->service_appointment_id) {
                $appointmentOrder = ServiceWorkOrder::query()->where('service_appointment_id', $approved->service_appointment_id)->first();
                if ($appointmentOrder) throw ValidationException::withMessages(['quote' => 'Este agendamento já foi convertido na OS #'.str_pad((string) $appointmentOrder->work_order_number, 5, '0', STR_PAD_LEFT).'.']);
            }
            if ($approved->items->isEmpty()) throw ValidationException::withMessages(['quote' => 'O orçamento aprovado não possui itens.']);

            $asset = null;
            if (! empty($data['service_asset_id'])) {
                $asset = ServiceAsset::query()->where('customer_id', $approved->customer_id)->where('status', 'active')->whereKey($data['service_asset_id'])->lockForUpdate()->first();
                if (! $asset) throw ValidationException::withMessages(['service_asset_id' => 'Selecione um veículo/equipamento ativo deste cliente.']);
            } elseif (! empty($data['save_asset'])) {
                $asset = ServiceAsset::create([
                    'company_id' => $company->id, 'customer_id' => $approved->customer_id,
                    'asset_type' => trim((string) ($data['asset_type'] ?? '')) ?: 'other',
                    'name' => trim((string) $data['asset_description']), 'manufacturer' => trim((string) ($data['asset_manufacturer'] ?? '')) ?: null,
                    'model' => trim((string) ($data['asset_model'] ?? '')) ?: null, 'year' => $data['asset_year'] ?? null,
                    'color' => trim((string) ($data['asset_color'] ?? '')) ?: null, 'identifier' => trim((string) ($data['asset_identifier'] ?? '')) ?: null,
                    'status' => 'active',
                ]);
            }

            $lockedCompany->increment('last_work_order_number');
            $lockedCompany->refresh();
            $order = ServiceWorkOrder::create([
                'company_id' => $company->id, 'work_order_number' => $lockedCompany->last_work_order_number,
                'customer_id' => $approved->customer_id, 'service_quote_id' => $approved->id,
                'service_appointment_id' => $approved->service_appointment_id,
                'service_asset_id' => $asset?->id,
                'asset_type' => $asset?->asset_type ?? (trim((string) ($data['asset_type'] ?? '')) ?: null),
                'asset_description' => $asset?->name ?? (trim((string) ($data['asset_description'] ?? '')) ?: null),
                'asset_identifier' => $asset?->identifier ?? (trim((string) ($data['asset_identifier'] ?? '')) ?: null),
                'asset_manufacturer' => $asset ? $asset->manufacturer : (trim((string) ($data['asset_manufacturer'] ?? '')) ?: null),
                'asset_model' => $asset ? $asset->model : (trim((string) ($data['asset_model'] ?? '')) ?: null),
                'asset_year' => $asset ? $asset->year : ($data['asset_year'] ?? null),
                'asset_color' => $asset ? $asset->color : (trim((string) ($data['asset_color'] ?? '')) ?: null),
                'complaint' => trim((string) ($data['complaint'] ?? '')) ?: null,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'status' => 'open', 'opened_at' => now($company->timezone), 'created_by' => $request->user()->id,
            ]);
            foreach ($approved->items as $item) {
                ServiceWorkOrderItem::create([
                    'company_id' => $company->id, 'service_work_order_id' => $order->id, 'item_type' => $item->item_type,
                    'product_id' => $item->product_id, 'name' => $item->name, 'description' => $item->description,
                    'quantity' => $item->quantity, 'unit_price_cents' => $item->unit_price_cents, 'total_cents' => $item->total_cents,
                ]);
            }
            if ($approved->service_appointment_id) {
                ServiceAppointment::query()->whereKey($approved->service_appointment_id)->whereIn('status', ['scheduled', 'confirmed'])->update(['status' => 'converted']);
            }
            $audit->record((int) $company->id, 'service.work_order_created', 'OS #'.$order->work_order_number.' criada a partir do orçamento #'.$approved->quote_number.'.', (int) $request->user()->id, 'service_work_order', (int) $order->id, ['quote_id' => (int) $approved->id, 'appointment_id' => $approved->service_appointment_id, 'asset_id' => $asset?->id, 'item_count' => $approved->items->count()]);

            return $order;
        });

        return redirect()->route('services.work-orders.show', $order->id)->with('work_order_notice', 'Ordem de serviço criada; estoque e financeiro não foram movimentados.');
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
            if ($credentials['password'] === '' || ! Hash::check($credentials['password'], $actor->password)) throw ValidationException::withMessages(['current_password' => 'Confirme sua senha de administrador para autorizar a operação.']);
            return (int) $actor->id;
        }
        if ($membership->role !== 'Atendente' || $credentials['email'] === '' || $credentials['password'] === '') {
            throw ValidationException::withMessages(['approval_password' => 'Informe o e-mail e a senha de um administrador ativo para liberar a operação.']);
        }
        $approver = User::query()->where('email', mb_strtolower($credentials['email']))->where('status', 'active')->first();
        $isCompanyAdmin = $approver && $approver->companyMemberships()->where('company_id', $companyId)->where('role', 'Administrador')->where('status', 'active')->exists();
        if (! $isCompanyAdmin || ! Hash::check($credentials['password'], $approver->password)) throw ValidationException::withMessages(['approval_password' => 'Não foi possível validar a autorização do administrador.']);
        return (int) $approver->id;
    }

    public function refund(Request $request, int $workOrder, CompanyContext $context, CashRegisterService $cash, AuditTrailService $audit): RedirectResponse
    {
        $credentials = $this->extractApprovalCredentials($request);
        $data = $request->validate([
            'request_key' => ['required', 'uuid'], 'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99', 'regex:/^\\d+(?:[.,]\\d{1,2})?$/'],
            'payment_method' => ['required', Rule::in(['cash', 'pix', 'credit_card', 'debit_card', 'bank_transfer', 'other'])],
            'refunded_at' => ['required', 'date', 'before_or_equal:now'], 'reference' => ['nullable', 'string', 'max:120'], 'reason' => ['required', 'string', 'min:5', 'max:180'],
        ]);
        $amountCents = (int) round((float) str_replace(',', '.', $data['amount']) * 100);
        try {
            $approverId = $this->authorizeCancellation($request, $credentials);
            DB::transaction(function () use ($workOrder, $context, $data, $amountCents, $request, $approverId, $cash, $audit): void {
                $order = ServiceWorkOrder::query()->whereKey($workOrder)->lockForUpdate()->firstOrFail();
                if ($order->status !== 'cancelled') throw ValidationException::withMessages(['refund' => 'Cancele a OS antes de registrar uma devolução.']);
                $existing = ServiceWorkOrderRefund::query()->where('request_key', $data['request_key'])->first();
                if ($existing) {
                    if ((int) $existing->service_work_order_id !== (int) $order->id) throw ValidationException::withMessages(['refund' => 'Esta operação já foi registrada em outra OS.']);
                    return;
                }
                $received = (int) AccountReceivablePayment::query()->whereHas('receivable', fn ($query) => $query->where('service_work_order_id', $order->id))->where('payment_method', $data['payment_method'])->sum('amount_cents');
                $refunded = (int) ServiceWorkOrderRefund::query()->where('service_work_order_id', $order->id)->where('payment_method', $data['payment_method'])->sum('amount_cents');
                if ($amountCents > $received - $refunded) throw ValidationException::withMessages(['amount' => 'O valor excede o total recebido por esta forma de pagamento, descontadas as devoluções anteriores.']);

                $session = null;
                if ($data['payment_method'] === 'cash') {
                    $session = CashSession::query()->where('status', 'open')->orderByDesc('id')->lockForUpdate()->first();
                    if (! $session) throw ValidationException::withMessages(['payment_method' => 'Abra o caixa antes de devolver dinheiro em espécie.']);
                    if ($amountCents > $cash->expectedBalance($session)) throw ValidationException::withMessages(['amount' => 'O saldo disponível no caixa é insuficiente para esta devolução.']);
                }

                $refund = ServiceWorkOrderRefund::create([
                    'company_id' => (int) $context->id(), 'service_work_order_id' => $order->id,
                    'payment_method' => $data['payment_method'], 'amount_cents' => $amountCents,
                    'reference' => $data['reference'] ?? null, 'reason' => trim($data['reason']), 'request_key' => $data['request_key'],
                    'cash_session_id' => $session?->id, 'created_by' => $request->user()->id, 'approved_by' => $approverId,
                    'refunded_at' => $data['payment_method'] === 'cash' ? now() : $data['refunded_at'],
                ]);
                if ($session) CashMovement::create([
                    'company_id' => (int) $context->id(), 'cash_session_id' => $session->id, 'direction' => 'out',
                    'movement_type' => 'service_work_order_refund', 'amount_cents' => $amountCents,
                    'description' => 'Devolução da OS #'.$order->work_order_number, 'source_type' => 'service_work_order_refund',
                    'source_id' => $refund->id, 'created_by' => $request->user()->id,
                ]);
                $audit->record((int) $context->id(), 'service.work_order_refunded', 'Devolução financeira da OS #'.$order->work_order_number.' registrada.', (int) $request->user()->id, 'service_work_order_refund', (int) $refund->id, ['work_order_id' => (int) $order->id, 'amount_cents' => $amountCents, 'payment_method' => $refund->payment_method, 'cash_session_id' => $refund->cash_session_id, 'reason' => $refund->reason], $approverId);
            });
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return redirect()->route('services.work-orders.show', $workOrder)->with('work_order_notice', 'Devolução registrada sem apagar o recebimento original.');
    }

    public function addItem(Request $request, int $workOrder, CompanyContext $context, AuditTrailService $audit): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(['product', 'service'])], 'product_id' => ['nullable', 'integer'], 'service_catalog_id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:1000'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'regex:/^\\d+(?:[.,]\\d{1,3})?$/'],
            'unit_price' => ['required_if:type,service', 'nullable', 'numeric', 'min:0', 'max:42949672.95', 'regex:/^\\d+(?:[.,]\\d{1,2})?$/'],
        ]);
        if ($data['type'] === 'product' && empty($data['product_id'])) throw ValidationException::withMessages(['product_id' => 'Selecione a peça/produto.']);
        if ($data['type'] === 'service' && trim((string) ($data['name'] ?? '')) === '') throw ValidationException::withMessages(['name' => 'Informe o serviço ou mão de obra.']);
        DB::transaction(function () use ($company, $workOrder, $data, $request, $audit): void {
            $order = ServiceWorkOrder::query()->whereKey($workOrder)->lockForUpdate()->firstOrFail();
            if (! in_array($order->status, ['open', 'diagnosis', 'awaiting_quote', 'quote', 'awaiting_approval', 'approved', 'in_maintenance', 'awaiting_part', 'in_progress'], true) || $order->stock_applied_at) {
                throw ValidationException::withMessages(['items' => 'Os itens só podem ser alterados antes da conclusão do serviço.']);
            }
            $product = null;
            $catalogService = null;
            if ($data['type'] === 'product') {
                $product = Product::query()->where('company_id', $company->id)->where('status', 'active')->whereKey($data['product_id'])->lockForUpdate()->first();
                if (! $product) throw ValidationException::withMessages(['product_id' => 'Produto não encontrado ou inativo.']);
            } else {
                if (! empty($data['service_catalog_id'])) {
                    $catalogService = ServiceCatalogItem::query()->where('company_id', $company->id)->where('status', 'active')->whereKey($data['service_catalog_id'])->lockForUpdate()->first();
                    if (! $catalogService) throw ValidationException::withMessages(['service_catalog_id' => 'Serviço não encontrado ou inativo.']);
                }
                if (! $catalogService && trim((string) ($data['name'] ?? '')) === '') throw ValidationException::withMessages(['name' => 'Informe o serviço ou mão de obra.']);
            }
            $quantity = (float) str_replace(',', '.', (string) $data['quantity']);
            $unitCents = $product ? (int) $product->sale_price_cents : (array_key_exists('unit_price', $data) && $data['unit_price'] !== null ? (int) round((float) str_replace(',', '.', (string) $data['unit_price']) * 100) : (int) ($catalogService?->price_cents ?? 0));
            if (! $product && $unitCents <= 0 && ! array_key_exists('unit_price', $data)) throw ValidationException::withMessages(['unit_price' => 'Informe o valor da mão de obra/serviço.']);
            $quantityMilli = (int) round($quantity * 1000);
            $item = ServiceWorkOrderItem::create([
                'company_id' => $company->id, 'service_work_order_id' => $order->id, 'item_type' => $data['type'],
                'product_id' => $product?->id, 'name' => $product?->name ?? $catalogService?->name ?? trim((string) $data['name']),
                'description' => trim((string) ($data['description'] ?? '')) ?: null, 'quantity' => $quantity,
                'unit_price_cents' => $unitCents, 'total_cents' => (int) round($quantityMilli * $unitCents / 1000),
            ]);
            $audit->record((int) $company->id, 'service.work_order_item_added', 'Item adicionado à OS #'.$order->work_order_number.'.', (int) $request->user()->id, 'service_work_order_item', (int) $item->id, ['work_order_id' => (int) $order->id, 'type' => $item->item_type]);
        });
        return back()->with('work_order_notice', 'Peça/serviço adicionado ao orçamento da OS.');
    }

    public function removeItem(Request $request, int $workOrder, int $item, CompanyContext $context, AuditTrailService $audit): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        DB::transaction(function () use ($company, $workOrder, $item, $request, $audit): void {
            $order = ServiceWorkOrder::query()->whereKey($workOrder)->lockForUpdate()->firstOrFail();
            if (! in_array($order->status, ['open', 'diagnosis', 'awaiting_quote', 'awaiting_approval', 'approved', 'in_maintenance', 'awaiting_part'], true) || $order->stock_applied_at) {
                throw ValidationException::withMessages(['items' => 'Os itens só podem ser alterados antes da conclusão do serviço.']);
            }
            $line = ServiceWorkOrderItem::query()->where('service_work_order_id', $order->id)->whereKey($item)->firstOrFail();
            $line->delete();
            $audit->record((int) $company->id, 'service.work_order_item_removed', 'Item removido da OS #'.$order->work_order_number.'.', (int) $request->user()->id, 'service_work_order', (int) $order->id, ['item_id' => (int) $item]);
        });
        return back()->with('work_order_notice', 'Item removido do orçamento da OS.');
    }

    public function updateStatus(Request $request, int $workOrder, CompanyContext $context, StockMovementService $stock, AuditTrailService $audit): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $credentials = $this->extractApprovalCredentials($request);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(self::STATUSES))], 'diagnosis' => ['required_if:status,awaiting_quote,quote', 'nullable', 'string', 'max:4000'], 'notes' => ['nullable', 'string', 'max:4000'], 'warranty_days' => ['nullable', 'integer', 'min:0', 'max:3650'], 'reason' => ['required_if:status,cancelled', 'nullable', 'string', 'min:5', 'max:180']]);
        $approverId = $data['status'] === 'cancelled' ? $this->authorizeCancellation($request, $credentials) : null;
        DB::transaction(function () use ($company, $workOrder, $data, $request, $stock, $audit, $approverId): void {
            $order = ServiceWorkOrder::query()->with('items')->whereKey($workOrder)->lockForUpdate()->firstOrFail();
            $current = $order->status;
            $target = $data['status'];
            $allowed = $order->stock_applied_at
                ? match ($current) {
                    'completed' => ['awaiting_payment', 'cancelled'], 'awaiting_payment' => ['ready', 'cancelled'],
                    'ready' => ['finalized', 'cancelled'], default => ['cancelled'],
                }
                : array_values(array_unique(array_merge(
                    array_diff(['open', 'diagnosis', 'awaiting_quote', 'quote', 'awaiting_approval', 'approved', 'in_maintenance', 'awaiting_part', 'in_progress', 'completed', 'awaiting_payment'], [$current]),
                    ['cancelled']
                )));
            if (! in_array($target, $allowed, true)) throw ValidationException::withMessages(['status' => 'Esta ordem não permite a mudança de situação solicitada.']);

            if ($target === 'awaiting_approval' && ! $order->items()->exists()) {
                throw ValidationException::withMessages(['status' => 'Adicione as peças e os serviços ao orçamento antes de enviá-lo para aprovação.']);
            }

            if ($target === 'ready') {
                $receivable = AccountReceivable::query()->where('service_work_order_id', $order->id)->lockForUpdate()->first();
                if (! $receivable || $receivable->status !== 'paid') throw ValidationException::withMessages(['status' => 'A OS só pode ser marcada pronta para retirada após a quitação do financeiro.']);
            }

            if ($target === 'awaiting_payment') {
                $receivable = AccountReceivable::query()->where('service_work_order_id', $order->id)->lockForUpdate()->first();
                if (! $receivable) {
                    $totalCents = (int) $order->items->sum('total_cents');
                    if ($totalCents <= 0) throw ValidationException::withMessages(['amount' => 'A OS precisa ter valor maior que zero para gerar a cobrança.']);
                    AccountReceivable::create([
                        'company_id' => $company->id, 'sale_id' => null, 'service_work_order_id' => $order->id,
                        'customer_id' => $order->customer_id, 'description' => 'Ordem de serviço #'.$order->work_order_number,
                        'installment_number' => 1, 'amount_cents' => $totalCents, 'paid_cents' => 0,
                        'cancelled_balance_cents' => 0, 'due_date' => now($company->timezone)->toDateString(), 'status' => 'pending',
                    ]);
                }
            }

            if ($target === 'cancelled') {
                $receivable = AccountReceivable::query()->where('service_work_order_id', $order->id)->lockForUpdate()->first();
                if ($receivable && in_array($receivable->status, ['pending', 'partial', 'paid'], true)) {
                    $receivable->update(['cancelled_balance_cents' => $receivable->remainingCents(), 'status' => 'cancelled']);
                }
            }

            if (in_array($target, ['completed', 'awaiting_payment', 'ready', 'finalized'], true) && ! $order->stock_applied_at) {
                foreach ($order->items->where('item_type', 'product') as $item) {
                    $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
                    $stock->record($product, 'exit', (float) $item->quantity, 'Consumo da OS #'.$order->work_order_number, $request->user()->id);
                }
                $order->stock_applied_at = now($company->timezone);
            }
            if ($target === 'cancelled' && $order->stock_applied_at && ! $order->stock_reversed_at) {
                foreach ($order->items->where('item_type', 'product') as $item) {
                    $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
                    $stock->record($product, 'entry', (float) $item->quantity, 'Estorno da OS #'.$order->work_order_number, $request->user()->id, true);
                }
                $order->stock_reversed_at = now($company->timezone);
            }
            $order->status = $target;
            if ($target === 'completed') $order->completed_at = now($company->timezone);
            if ($target === 'finalized') $order->finalized_at = now($company->timezone);
            if ($target === 'cancelled') {
                $order->cancelled_at = now($company->timezone);
                $order->cancelled_by = $request->user()->id;
                $order->cancelled_approved_by = $approverId;
                $order->cancellation_reason = trim((string) $data['reason']);
            }
            if (array_key_exists('diagnosis', $data)) $order->diagnosis = trim((string) $data['diagnosis']) ?: null;
            if (array_key_exists('notes', $data)) $order->notes = trim((string) $data['notes']) ?: null;
            if (array_key_exists('warranty_days', $data)) $order->warranty_days = (int) $data['warranty_days'];
            $order->save();
            $audit->record((int) $company->id, $target === 'cancelled' ? 'service.work_order_cancelled' : 'service.work_order_status_changed', 'Situação da OS #'.$order->work_order_number.' alterada.', (int) $request->user()->id, 'service_work_order', (int) $order->id, ['before' => $current, 'after' => $target, 'reason' => $target === 'cancelled' ? $order->cancellation_reason : null, 'stock_applied' => (bool) $order->stock_applied_at, 'stock_reversed' => (bool) $order->stock_reversed_at], $approverId);
        });

        return back()->with('work_order_notice', 'Situação da ordem de serviço atualizada.');
    }
}
