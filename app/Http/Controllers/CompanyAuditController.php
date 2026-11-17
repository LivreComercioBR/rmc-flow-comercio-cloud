<?php

namespace App\Http\Controllers;

use App\Models\CompanyAuditEvent;
use App\Models\CompanyMembership;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CompanyAuditController extends Controller
{
    public function index(Request $request, CompanyContext $context): View
    {
        $company = $context->company();
        abort_unless($company, 403);
        abort_unless($request->user()->companyMemberships()->where('company_id', $company->id)->where('status', 'active')->where('role', 'Administrador')->exists(), 403);
        $filters = $request->validate([
            'event_key' => ['nullable', 'string', 'max:80'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $events = CompanyAuditEvent::query()->with(['actor', 'approver'])
            ->when(! empty($filters['event_key']), fn ($query) => $query->where('event_key', $filters['event_key']))
            ->when(! empty($filters['user_id']), fn ($query) => $query->where(fn ($q) => $q->where('actor_id', $filters['user_id'])->orWhere('approved_by', $filters['user_id'])))
            ->when(! empty($filters['from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->latest('created_at')->latest('id')->paginate(30)->withQueryString();
        $users = CompanyMembership::query()->with('user')->where('company_id', $company->id)->get()->pluck('user')->filter()->unique('id')->sortBy('name');
        $eventTypes = CompanyAuditEvent::query()->select('event_key')->distinct()->orderBy('event_key')->pluck('event_key');
        return view('companies.audit', compact('company', 'events', 'users', 'eventTypes', 'filters'));
    }
}
