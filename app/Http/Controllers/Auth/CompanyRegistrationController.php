<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\CompanyModule;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

final class CompanyRegistrationController extends Controller
{
    public function create(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('companies.index') : view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('document')) {
            $document = preg_replace('/\\D+/', '', $request->input('document'));
            $request->merge(['document' => $document !== '' ? $document : null]);
        }

        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:190'],
            'trade_name' => ['required', 'string', 'max:190'],
            'document' => ['nullable', 'string', 'max:32', 'unique:companies,document'],
            'activity' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        [$user, $company] = DB::transaction(function () use ($data): array {
            $company = Company::create([
                'legal_name' => trim($data['legal_name']),
                'trade_name' => trim($data['trade_name']),
                'document' => isset($data['document']) ? preg_replace('/\D+/', '', $data['document']) : null,
                'email' => mb_strtolower(trim($data['email'])),
                'status' => 'active',
            ]);

            $user = User::create([
                'name' => trim($data['name']),
                'email' => mb_strtolower(trim($data['email'])),
                'password' => $data['password'],
                'status' => 'active',
            ]);

            CompanyMembership::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role' => 'Administrador',
                'status' => 'active',
            ]);

            CompanyModule::create([
                'company_id' => $company->id,
                'module_key' => 'services',
                'enabled' => false,
                'source' => 'company',
            ]);

            CompanySetting::create([
                'company_id' => $company->id,
                'setting_key' => 'business.activity',
                'setting_value' => ['value' => trim($data['activity'] ?? '')],
            ]);

            return [$user, $company];
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('active_company_id', $company->id);

        if (in_array(config('mail.default'), ['smtp', 'ses', 'ses-v2', 'mailgun', 'postmark', 'resend', 'sendmail'], true)) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $exception) {
                Log::warning('Registration verification email delivery failed.', ['exception_type' => get_class($exception)]);
            }
        }

        return redirect()->route('verification.notice');
    }
}

