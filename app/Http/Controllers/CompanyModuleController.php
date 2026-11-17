<?php

namespace App\Http\Controllers;

use App\Models\CompanyModule;
use App\Support\CompanyContext;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CompanyModuleController extends Controller
{
    public function update(Request $request, CompanyContext $context): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        abort_unless($request->user()->companyMemberships()->where('company_id', $company->id)->where('status', 'active')->where('role', 'Administrador')->exists(), 403);
        $data = $request->validate(['services_enabled' => ['required', 'boolean']]);

        DB::transaction(function () use ($company, $data, $request): void {
            $current = CompanyModule::query()->firstOrNew(['company_id' => $company->id, 'module_key' => 'services']);
            $before = (bool) $current->enabled;
            $current->fill(['enabled' => (bool) $data['services_enabled'], 'source' => 'company', 'changed_by' => $request->user()->id])->save();
            app(AuditTrailService::class)->record((int) $company->id, 'company.module_updated', 'Preferência do módulo de serviços alterada.', (int) $request->user()->id, 'company_module', (int) $current->id, ['module_key' => 'services', 'before' => $before, 'after' => (bool) $current->enabled]);
        });

        return back()->with('module_notice', 'Preferência do módulo de serviços salva. Os recursos operacionais serão liberados conforme forem concluídos.');
    }
}
