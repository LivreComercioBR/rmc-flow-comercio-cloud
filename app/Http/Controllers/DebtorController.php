<?php

namespace App\Http\Controllers;

use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DebtorController extends Controller
{
    public function index(Request $request, CompanyContext $context): View
    {
        $companyId = $context->id();
        abort_unless($companyId, 403);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = trim((string) ($filters['q'] ?? ''));
        $openBalance = AccountReceivable::query()->selectRaw('COALESCE(SUM(amount_cents - paid_cents - cancelled_balance_cents), 0)')
            ->where(function ($query): void {
                $query->whereColumn('customer_id', 'customers.id')
                    ->orWhereHas('sale', fn ($sale) => $sale->whereColumn('customer_id', 'customers.id'));
            })->whereIn('status', ['pending', 'partial'])
            ->whereRaw('amount_cents > paid_cents + cancelled_balance_cents');
        $overdueCount = AccountReceivable::query()->selectRaw('COUNT(*)')
            ->where(function ($query): void {
                $query->whereColumn('customer_id', 'customers.id')
                    ->orWhereHas('sale', fn ($sale) => $sale->whereColumn('customer_id', 'customers.id'));
            })->whereIn('status', ['pending', 'partial'])
            ->where('due_date', '<', now()->toDateString())->whereRaw('amount_cents > paid_cents + cancelled_balance_cents');

        $debtors = Customer::query()->where('company_id', $companyId)
            ->where(function ($query): void {
                $query->whereHas('receivables', fn ($receivable) => $receivable->whereIn('status', ['pending', 'partial'])->whereRaw('amount_cents > paid_cents + cancelled_balance_cents'))
                    ->orWhereHas('sales.receivables', fn ($receivable) => $receivable->whereIn('status', ['pending', 'partial'])->whereRaw('amount_cents > paid_cents + cancelled_balance_cents'));
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($matches) use ($search): void {
                    $matches->where('name', 'like', '%'.$search.'%')
                        ->orWhere('document', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            })
            ->select('customers.*')->selectSub($openBalance, 'open_balance_cents')->selectSub($overdueCount, 'overdue_count')
            ->orderByDesc('open_balance_cents')->orderBy('name')->paginate(25)->withQueryString();

        return view('debtors.index', compact('debtors', 'search'));
    }
}
