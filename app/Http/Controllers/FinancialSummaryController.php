<?php

namespace App\Http\Controllers;

use App\Models\AccountReceivablePayment;
use App\Models\CashMovement;
use App\Models\SalePayment;
use App\Models\SaleRefund;
use App\Models\ServiceWorkOrderRefund;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class FinancialSummaryController extends Controller
{
    private const METHODS = ['cash', 'pix', 'debit_card', 'credit_card', 'bank_transfer', 'other'];

    public function __invoke(Request $request, CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $filters = $request->validate([
            'period' => ['nullable', Rule::in(['today', 'week', 'month', 'year', 'custom'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        [$from, $to] = $this->period($filters);
        $rows = collect(self::METHODS)->mapWithKeys(fn ($method) => [$method => ['received' => 0, 'refunded' => 0, 'net' => 0]]);
        $this->addTotals($rows, SalePayment::query()->whereBetween('paid_at', [$from, $to]), 'received');
        $this->addTotals($rows, AccountReceivablePayment::query()->whereBetween('paid_at', [$from, $to]), 'received');
        $this->addTotals($rows, SaleRefund::query()->whereBetween('refunded_at', [$from, $to]), 'refunded');
        $this->addTotals($rows, ServiceWorkOrderRefund::query()->whereBetween('refunded_at', [$from, $to]), 'refunded');
        $rows = $rows->map(function (array $row): array { $row['net'] = $row['received'] - $row['refunded']; return $row; });

        $cashIn = (int) CashMovement::query()->where('direction', 'in')->whereBetween('created_at', [$from, $to])->sum('amount_cents');
        $cashOut = (int) CashMovement::query()->where('direction', 'out')->whereBetween('created_at', [$from, $to])->sum('amount_cents');
        $totals = ['received' => $rows->sum('received'), 'refunded' => $rows->sum('refunded'), 'net' => $rows->sum('net')];
        return view('financial.summary', compact('filters', 'from', 'to', 'rows', 'totals', 'cashIn', 'cashOut'));
    }

    private function period(array $filters): array
    {
        $period = $filters['period'] ?? 'month';
        $today = now()->startOfDay();
        return match ($period) {
            'today' => [$today, $today->copy()->endOfDay()],
            'week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'custom' => [Carbon::parse($filters['from'] ?? $today)->startOfDay(), Carbon::parse($filters['to'] ?? $today)->endOfDay()],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
        };
    }

    private function addTotals($rows, $query, string $column): void
    {
        foreach ($query->selectRaw('payment_method, SUM(amount_cents) as total_cents')->groupBy('payment_method')->get() as $record) {
            if ($rows->has($record->payment_method)) $rows[$record->payment_method][$column] += (int) $record->total_cents;
        }
    }
}
