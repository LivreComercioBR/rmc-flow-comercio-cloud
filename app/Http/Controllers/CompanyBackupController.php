<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CashSession;
use App\Services\AuditTrailService;
use App\Services\CompanyBackupService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class CompanyBackupController extends Controller
{
    public function index(CompanyContext $context): View
    {
        $company = $context->company();
        abort_unless($company, 403);
        $this->ensureAdministrator($company, request());

        return view('companies.backup');
    }

    public function download(CompanyContext $context, CompanyBackupService $backups): StreamedResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $this->ensureAdministrator($company, request());
        $fileName = 'rmc-flow-backup-empresa-'.$company->id.'-'.now()->format('Ymd-His').'.json';
        $contents = $backups->create($company);

        return response()->streamDownload(static function () use ($contents): void {
            echo $contents;
        }, $fileName, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public function restore(Request $request, CompanyContext $context, CompanyBackupService $backups, AuditTrailService $audit): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $this->ensureAdministrator($company, $request);
        $data = $request->validate([
            'backup_file' => ['required', 'file', 'mimes:json', 'max:30720'],
            'confirmation' => ['required', 'in:RESTAURAR'],
        ]);

        if (CashSession::query()->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['backup_file' => 'Feche o caixa aberto antes de restaurar um backup.']);
        }

        $restoredCount = $backups->restore($company, (string) file_get_contents($data['backup_file']->getRealPath()));
        $audit->record((int) $company->id, 'company.backup_restored', 'Backup da empresa restaurado.', (int) $request->user()->id, 'company', (int) $company->id, ['restored_rows' => $restoredCount]);

        return back()->with('backup_notice', 'Backup restaurado. '.$restoredCount.' registros foram recuperados.');
    }

    private function ensureAdministrator(Company $company, Request $request): void
    {
        abort_unless($request->user()->companyMemberships()->where('company_id', $company->id)->where('status', 'active')->where('role', 'Administrador')->exists(), 403);
    }
}
