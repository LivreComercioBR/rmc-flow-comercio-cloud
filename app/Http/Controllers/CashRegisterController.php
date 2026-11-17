<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\SaleRefund;
use App\Models\ServiceWorkOrderRefund;
use App\Models\CompanyMembership;
use App\Services\Cash\CashRegisterService;
use App\Support\CompanyContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class CashRegisterController extends Controller
{
    public function index(CompanyContext $context, CashRegisterService $cash): View
    {
        abort_unless($context->id(), 403);
        $openSession = CashSession::query()->where('status', 'open')->with(['opener', 'movements.creator'])->first();
        $expectedCents = $openSession ? $cash->expectedBalance($openSession) : null;
        $history = CashSession::query()->where('status', 'closed')->with(['opener', 'closer'])->latest('closed_at')->paginate(15);
        $role = CompanyMembership::query()->where('company_id', $context->id())->where('user_id', auth()->id())->where('status', 'active')->value('role');
        $refundIds = $openSession?->movements->where('source_type', 'sale_refund')->pluck('source_id')->filter()->unique() ?? collect();
        $refundsById = SaleRefund::query()->with(['creator', 'approver'])->whereIn('id', $refundIds)->get()->keyBy('id');
        $workOrderRefundIds = $openSession?->movements->where('source_type', 'service_work_order_refund')->pluck('source_id')->filter()->unique() ?? collect();
        $workOrderRefundsById = ServiceWorkOrderRefund::query()->with(['creator', 'approver', 'workOrder'])->whereIn('id', $workOrderRefundIds)->get()->keyBy('id');
        return view('cash.index', compact('openSession', 'expectedCents', 'history', 'role', 'refundsById', 'workOrderRefundsById'));
    }

    public function show(int $session, CompanyContext $context, CashRegisterService $cash): View
    {
        abort_unless($context->id(), 403);
        $record = CashSession::query()->with(['opener', 'closer', 'movements.creator'])->findOrFail($session);
        $calculatedCents = $cash->expectedBalance($record);
        $refundIds = $record->movements->where('source_type', 'sale_refund')->pluck('source_id')->filter()->unique();
        $refundsById = SaleRefund::query()->with(['creator', 'approver', 'sale'])->whereIn('id', $refundIds)->get()->keyBy('id');
        $workOrderRefundIds = $record->movements->where('source_type', 'service_work_order_refund')->pluck('source_id')->filter()->unique();
        $workOrderRefundsById = ServiceWorkOrderRefund::query()->with(['creator', 'approver', 'workOrder'])->whereIn('id', $workOrderRefundIds)->get()->keyBy('id');
        return view('cash.show', ['cashSession' => $record, 'calculatedCents' => $calculatedCents, 'refundsById' => $refundsById, 'workOrderRefundsById' => $workOrderRefundsById]);
    }

    public function open(Request $request, CompanyContext $context, CashRegisterService $cash): RedirectResponse
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);
        $data = $request->validate([
            'opening_amount' => ['required', 'numeric', 'min:0', 'max:42949672.95', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
        ]);
        try {
            $cash->open($companyId, $this->toCents($data['opening_amount']), $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }
        return redirect()->route('cash.index')->with('cash_notice', 'Caixa aberto.');
    }

    public function movement(Request $request, int $session, CompanyContext $context, CashRegisterService $cash): RedirectResponse
    {
        $record = $this->findOpenSession($session, $context);
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'movement_type' => ['required', Rule::in(['entry', 'supply', 'expense', 'withdrawal'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:42949672.95', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
            'description' => ['required', 'string', 'max:180'],
        ]);
        try {
            $cash->recordManual($record->id, $data['movement_type'], $this->toCents($data['amount']), $data['description'], $data['request_key'], $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        } catch (QueryException $exception) {
            $existing = \App\Models\CashMovement::query()->where('request_key', $data['request_key'])->first();
            if (! $existing || (int) $existing->cash_session_id !== $record->id) throw $exception;
        }
        return redirect()->route('cash.index')->with('cash_notice', 'Movimentação registrada.');
    }

    public function close(Request $request, int $session, CompanyContext $context, CashRegisterService $cash): RedirectResponse
    {
        $record = $this->findOpenSession($session, $context);
        $data = $request->validate([
            'counted_amount' => ['required', 'numeric', 'min:0', 'max:42949672.95', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
            'closing_note' => ['nullable', 'string', 'max:180'],
        ]);
        $closed = $cash->close($record->id, $this->toCents($data['counted_amount']), $request->user()->id, $data['closing_note'] ?? null);
        return redirect()->route('cash.index')->with('cash_notice', 'Caixa fechado. Diferença apurada: R$ '.number_format(((int) $closed->difference_cents) / 100, 2, ',', '.').'.');
    }

    private function findOpenSession(int $session, CompanyContext $context): CashSession
    {
        abort_unless($context->id(), 403);
        return CashSession::query()->where('status', 'open')->findOrFail($session);
    }

    private function toCents(mixed $value): int
    {
        return (int) round((float) str_replace(',', '.', (string) ($value ?: '0')) * 100);
    }
}
