<?php

namespace App\Http\Controllers;

use App\Models\AccountPayable;
use App\Models\AccountPayablePayment;
use App\Models\AccountReceivable;
use App\Models\AccountReceivablePayment;
use App\Models\CashMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleRefund;
use App\Models\ServiceWorkOrderRefund;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ReportController extends Controller
{
    public function __invoke(Request $request, CompanyContext $context): View
    {
        abort_unless($context->id(), 403);
        $filters = $request->validate([
            'period' => ['nullable', Rule::in(['today', 'week', 'month', 'year', 'custom'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        [$from, $to] = $this->period($filters);
        $sales = Sale::query()->where('status', 'completed')->whereBetween('sold_at', [$from, $to]);
        $saleCount = (clone $sales)->count();
        $salesTotal = (int) (clone $sales)->sum('total_cents');
        $received = (int) SalePayment::query()->whereBetween('paid_at', [$from, $to])->sum('amount_cents')
            + (int) AccountReceivablePayment::query()->whereBetween('paid_at', [$from, $to])->sum('amount_cents');
        $refunded = (int) SaleRefund::query()->whereBetween('refunded_at', [$from, $to])->sum('amount_cents')
            + (int) ServiceWorkOrderRefund::query()->whereBetween('refunded_at', [$from, $to])->sum('amount_cents');
        $paidPayables = (int) AccountPayablePayment::query()->whereBetween('paid_at', [$from, $to])->sum('amount_cents');
        $manualOut = (int) CashMovement::query()->where('direction', 'out')->whereIn('movement_type', ['cash_out', 'withdrawal'])->whereBetween('created_at', [$from, $to])->sum('amount_cents');
        $paidExpenses = $paidPayables + $manualOut;
        $netCashFlow = $received - $refunded - $paidExpenses;
        $incomeCategory = "CASE WHEN accounts_receivable.sale_id IS NOT NULL THEN 'Vendas no PDV' ELSE COALESCE(finance_categories_income.name, 'Sem categoria') END";
        $incomeByCategory = AccountReceivablePayment::query()->join('accounts_receivable', function ($join): void {
            $join->on('accounts_receivable.id', '=', 'account_receivable_payments.account_receivable_id')->on('accounts_receivable.company_id', '=', 'account_receivable_payments.company_id');
        })->leftJoin('categories as finance_categories_income', function ($join): void {
            $join->on('finance_categories_income.id', '=', 'accounts_receivable.financial_category_id')->on('finance_categories_income.company_id', '=', 'accounts_receivable.company_id');
        })->whereBetween('account_receivable_payments.paid_at', [$from, $to])
            ->selectRaw($incomeCategory.' as category_name, SUM(account_receivable_payments.amount_cents) as total_cents')
            ->groupByRaw($incomeCategory)->orderByDesc('total_cents')->get();
        $salePaymentsIncome = (int) SalePayment::query()->whereBetween('paid_at', [$from, $to])->sum('amount_cents');
        if ($salePaymentsIncome > 0) $incomeByCategory->push((object) ['category_name' => 'Vendas no PDV', 'total_cents' => $salePaymentsIncome]);
        $incomeByCategory = $incomeByCategory->groupBy('category_name')->map(fn ($items) => (object) [
            'category_name' => $items->first()->category_name,
            'total_cents' => $items->sum(fn ($item) => (int) $item->total_cents),
        ])->sortByDesc('total_cents')->values();
        $expenseByCategory = AccountPayablePayment::query()->join('account_payables', function ($join): void {
            $join->on('account_payables.id', '=', 'account_payable_payments.account_payable_id')->on('account_payables.company_id', '=', 'account_payable_payments.company_id');
        })->leftJoin('categories as finance_categories_expense', function ($join): void {
            $join->on('finance_categories_expense.id', '=', 'account_payables.financial_category_id')->on('finance_categories_expense.company_id', '=', 'account_payables.company_id');
        })->whereBetween('account_payable_payments.paid_at', [$from, $to])
            ->selectRaw("COALESCE(finance_categories_expense.name, 'Sem categoria') as category_name, SUM(account_payable_payments.amount_cents) as total_cents")
            ->groupBy('finance_categories_expense.name')->orderByDesc('total_cents')->get();
        $openReceivables = (int) AccountReceivable::query()->whereIn('status', ['pending', 'partial'])->whereDate('due_date', '<=', $to->toDateString())->selectRaw('COALESCE(SUM(amount_cents - paid_cents - cancelled_balance_cents),0) as total')->value('total');
        $openPayables = (int) AccountPayable::query()->whereIn('status', ['pending', 'partial'])->whereDate('due_date', '<=', $to->toDateString())->selectRaw('COALESCE(SUM(amount_cents - paid_cents - cancelled_balance_cents),0) as total')->value('total');

        $topItems = SaleItem::query()->join('sales', function ($join): void {
            $join->on('sales.id', '=', 'sale_items.sale_id')->on('sales.company_id', '=', 'sale_items.company_id');
        })->where('sales.status', 'completed')->whereBetween('sales.sold_at', [$from, $to])
            ->selectRaw('sale_items.product_id, sale_items.product_name, SUM(sale_items.quantity) as quantity_sold, SUM(sale_items.line_total_cents) as gross_cents')
            ->groupBy('sale_items.product_id', 'sale_items.product_name')->orderByDesc('quantity_sold')->limit(10)->get();
        $lowStock = Product::query()->where('status', 'active')->where('minimum_stock', '>', 0)->whereColumn('stock_quantity', '<=', 'minimum_stock')->orderBy('stock_quantity')->limit(20)->get();

        return view('reports.index', compact('filters', 'from', 'to', 'saleCount', 'salesTotal', 'received', 'refunded', 'paidExpenses', 'netCashFlow', 'openReceivables', 'openPayables', 'topItems', 'lowStock', 'incomeByCategory', 'expenseByCategory'));
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
}
