<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Support\CompanyContext;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CompanySettingsController extends Controller
{
    public function edit(CompanyContext $context): View
    {
        $company = $context->company();
        abort_unless($company, 403);
        abort_unless(auth()->user()->companyMemberships()->where('company_id', $company->id)->where('status', 'active')->where('role', 'Administrador')->exists(), 403);
        $settings = $company->settings()->whereIn('setting_key', ['business.activity', 'company.address'])->pluck('setting_value', 'setting_key');
        $address = $settings->get('company.address') ?? [];
        $activity = $settings->get('business.activity') ?? [];
        $servicesEnabled = (bool) $company->modules()->where('module_key', 'services')->value('enabled');
        $logoPath = $company->logoPath();
        $logoUrl = $company->logoUrl();

        return view('companies.settings', compact('company', 'address', 'activity', 'servicesEnabled', 'logoPath', 'logoUrl'));
    }

    public function update(Request $request, CompanyContext $context): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        abort_unless($request->user()->companyMemberships()->where('company_id', $company->id)->where('status', 'active')->where('role', 'Administrador')->exists(), 403);
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:190'],
            'trade_name' => ['required', 'string', 'max:190'],
            'document' => ['nullable', 'string', 'max:32', Rule::unique('companies', 'document')->ignore($company->id)],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
            'activity' => ['nullable', 'string', 'max:120'],
            'street' => ['nullable', 'string', 'max:190'],
            'number' => ['nullable', 'string', 'max:30'],
            'complement' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'size:2'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=3000,max_height=3000'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);
        if ($request->boolean('remove_logo') && $request->hasFile('logo')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['logo' => 'Envie uma logo nova ou marque a remoção da logo atual, não as duas opções.']);
        }
        $normalizedDocument = preg_replace('/\D+/', '', (string) ($data['document'] ?? ''));
        $data['document'] = $normalizedDocument !== '' ? $normalizedDocument : null;
        $address = collect(['street', 'number', 'complement', 'district', 'city', 'state', 'postal_code'])
            ->mapWithKeys(fn ($field) => [$field => trim((string) ($data[$field] ?? ''))])->all();

        $oldLogoPath = $company->logoPath();
        $removeLogo = $request->boolean('remove_logo');
        $logoChanged = $request->hasFile('logo') || $removeLogo;
        $logoAction = $request->hasFile('logo') ? 'updated' : ($removeLogo ? 'removed' : null);
        $newLogoPath = null;

        if ($request->hasFile('logo')) {
            $newLogoPath = $request->file('logo')->storePublicly('companies/'.$company->id.'/logos', 'company_logos');
            if (! is_string($newLogoPath) || $newLogoPath === '') {
                return back()->withErrors(['logo' => 'Não foi possível salvar a logo. Tente novamente.'])->withInput();
            }
        }

        try {
            DB::transaction(function () use ($company, $data, $address, $request, $newLogoPath, $removeLogo, $logoAction): void {
            $before = $company->only(['legal_name', 'trade_name', 'document', 'email', 'phone']);
            $company->update([
                'legal_name' => trim($data['legal_name']),
                'trade_name' => trim($data['trade_name']),
                'document' => $data['document'],
                'email' => isset($data['email']) ? mb_strtolower(trim($data['email'])) : null,
                'phone' => isset($data['phone']) ? trim($data['phone']) : null,
            ]);
            CompanySetting::updateOrCreate(['company_id' => $company->id, 'setting_key' => 'business.activity'], ['setting_value' => ['value' => trim($data['activity'] ?? '')]]);
            CompanySetting::updateOrCreate(['company_id' => $company->id, 'setting_key' => 'company.address'], ['setting_value' => $address]);
            if ($newLogoPath) {
                CompanySetting::updateOrCreate(
                    ['company_id' => $company->id, 'setting_key' => 'company.logo'],
                    ['setting_value' => ['path' => $newLogoPath]],
                );
            } elseif ($removeLogo) {
                CompanySetting::query()->where('company_id', $company->id)->where('setting_key', 'company.logo')->delete();
            }
            app(AuditTrailService::class)->record((int) $company->id, 'company.settings_updated', 'Dados comerciais da empresa atualizados.', (int) $request->user()->id, 'company', (int) $company->id, ['before' => $before, 'after' => $company->only(['legal_name', 'trade_name', 'document', 'email', 'phone']), 'address' => $address, 'activity' => trim($data['activity'] ?? ''), 'logo_action' => $logoAction]);
            });
        } catch (\Throwable $exception) {
            if ($newLogoPath) Storage::disk('company_logos')->delete($newLogoPath);
            throw $exception;
        }

        if ($logoChanged && $oldLogoPath && $oldLogoPath !== $newLogoPath) {
            // Apaga tanto o armazenamento atual quanto o local legado do primeiro deploy.
            Storage::disk('company_logos')->delete($oldLogoPath);
            Storage::disk('public')->delete($oldLogoPath);
        }

        $notice = match ($logoAction) {
            'updated' => 'Dados da empresa e logo atualizados.',
            'removed' => 'Dados da empresa atualizados e logo removida.',
            default => 'Dados da empresa atualizados.',
        };

        return back()->with('settings_notice', $notice);
    }
}
