<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\CompanyModule;
use App\Models\CompanySetting;
use App\Models\PaymentEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionCheckoutIntent;
use App\Models\User;
use App\Services\MercadoPago\PaymentGateway;
use App\Services\SubscriptionPanel\SubscriptionSnapshotSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

final class SubscriptionPurchaseController extends Controller
{
    public function plans(): View
    {
        return view('checkout.plans', ['plans' => Plan::query()->where('active', true)->orderBy('initial_price_cents')->get()]);
    }

    public function start(Request $request, PaymentGateway $gateway): RedirectResponse
    {
        abort_unless(config('services.mercado_pago.checkout_enabled', false), 503, 'A contratação ainda não está disponível.');

        $data = $request->validate([
            'plan_code' => ['required', 'string', 'max:64', Rule::exists('plans', 'code')->where('active', true)],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
        ], [
            'email.unique' => 'Este endereço de e-mail já está cadastrado. Para continuar, use outro e-mail.',
        ]);
        $plan = Plan::query()->where('code', $data['plan_code'])->where('active', true)->firstOrFail();
        $email = mb_strtolower(trim($data['email']));
        $token = Str::random(64);
        $intent = SubscriptionCheckoutIntent::create([
            'token' => $token,
            'plan_id' => $plan->id,
            'contact_email' => $email,
            'purchase_reference' => 'purchase:'.Str::uuid(),
            'expected_amount_cents' => $plan->initial_price_cents,
            'status' => 'creating',
            'expires_at' => now()->addDays(3),
        ]);

        $returnUrl = route('checkout.return', ['token' => $token]);
        $notificationUrl = (string) config('services.mercado_pago.notification_url', '');
        abort_unless(filter_var($returnUrl, FILTER_VALIDATE_URL) && str_starts_with($returnUrl, 'https://'), 503, 'APP_URL deve usar o domínio HTTPS público antes de iniciar o checkout.');
        abort_unless(filter_var($notificationUrl, FILTER_VALIDATE_URL) && str_starts_with($notificationUrl, 'https://'), 503, 'Configure a URL HTTPS pública do webhook do Mercado Pago.');

        $payload = [
            'items' => [[
                'id' => $plan->code.'-initial',
                'title' => $plan->name.' - primeira compra anual',
                'description' => 'Até '.($plan->limits['users'] ?? 'ilimitados').' usuários; computadores ilimitados.',
                'quantity' => 1,
                'currency_id' => 'BRL',
                'unit_price' => $plan->initial_price_cents / 100,
            ]],
            'payer' => ['email' => $email],
            'external_reference' => $intent->purchase_reference,
            'back_urls' => [
                'success' => $returnUrl.'?result=success',
                'pending' => $returnUrl.'?result=pending',
                'failure' => $returnUrl.'?result=failure',
            ],
            'auto_return' => 'approved',
            'expires' => true,
            'expiration_date_from' => now()->toIso8601String(),
            'expiration_date_to' => now()->addDays(3)->toIso8601String(),
            'metadata' => [
                'checkout_intent_id' => $intent->id,
                'product_code' => (string) config('services.subscription_panel.product_code'),
                'billing_stage' => 'initial',
                'plan_code' => $plan->code,
                'expected_amount_cents' => $plan->initial_price_cents,
                'purchase_reference' => $intent->purchase_reference,
            ],
        ];
        if (! config('services.mercado_pago.sandbox', false)) {
            $payload['notification_url'] = $notificationUrl;
        }

        try {
            $checkout = $gateway->createCheckout($payload);
        } catch (Throwable $exception) {
            $intent->update(['status' => 'checkout_error']);
            report($exception);
            return back()->withErrors(['checkout' => 'Não foi possível iniciar a contratação agora. Tente novamente em alguns minutos.']);
        }

        $checkoutUrl = $checkout['init_point'] ?? null;
        abort_unless(is_string($checkoutUrl) && filter_var($checkoutUrl, FILTER_VALIDATE_URL), 502, 'O Mercado Pago não retornou uma URL de checkout válida.');
        $intent->update([
            'preference_id' => $checkout['id'] ?? null,
            'checkout_url' => $checkoutUrl,
            'status' => 'pending',
            'metadata' => ['checkout_created_at' => now()->toIso8601String()],
        ]);

        return redirect()->away($checkoutUrl);
    }

    public function returned(string $token): View|RedirectResponse
    {
        $intent = SubscriptionCheckoutIntent::query()->with('plan')->where('token', $token)->firstOrFail();
        if ($intent->status === 'approved' && config('services.mercado_pago.activate_approved_payments', false)) {
            return redirect()->route('checkout.register', $intent->token);
        }

        return view('checkout.return', compact('intent'));
    }

    public function registration(string $token): View
    {
        $intent = SubscriptionCheckoutIntent::query()->with('plan')->where('token', $token)->firstOrFail();
        abort_unless($intent->status === 'approved' && config('services.mercado_pago.activate_approved_payments', false), 403, 'O pagamento precisa ser confirmado antes do cadastro.');
        abort_if($intent->registered_at, 409, 'Este pagamento já foi vinculado a um cadastro.');

        return view('checkout.register', compact('intent'));
    }

    public function complete(Request $request, string $token, SubscriptionSnapshotSync $panelSync): RedirectResponse
    {
        $intent = SubscriptionCheckoutIntent::query()->with('plan')->where('token', $token)->firstOrFail();
        abort_unless($intent->status === 'approved' && config('services.mercado_pago.activate_approved_payments', false), 403, 'O pagamento precisa ser confirmado antes do cadastro.');
        abort_if($intent->registered_at, 409, 'Este pagamento já foi vinculado a um cadastro.');

        if ($request->filled('document')) {
            $document = preg_replace('/\D+/', '', $request->input('document'));
            $request->merge(['document' => $document !== '' ? $document : null]);
        }
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:190'],
            'trade_name' => ['required', 'string', 'max:190'],
            'document' => ['nullable', 'string', 'max:32', 'unique:companies,document'],
            'activity' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        [$user, $company, $event] = DB::transaction(function () use ($data, $intent): array {
            $lockedIntent = SubscriptionCheckoutIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedIntent->status === 'approved' && ! $lockedIntent->registered_at, 409);
            abort_if(User::query()->where('email', $lockedIntent->contact_email)->exists(), 422, 'Este e-mail já possui uma conta. Entre nela para vincular uma nova empresa ou informe outro e-mail antes de iniciar uma nova contratação.');

            $company = Company::create([
                'legal_name' => trim($data['legal_name']),
                'trade_name' => trim($data['trade_name']),
                'document' => isset($data['document']) ? preg_replace('/\D+/', '', $data['document']) : null,
                'email' => $lockedIntent->contact_email,
                'status' => 'active',
            ]);
            $user = User::create([
                'name' => trim($data['name']),
                'email' => $lockedIntent->contact_email,
                'password' => $data['password'],
                'status' => 'active',
            ]);
            CompanyMembership::create(['company_id' => $company->id, 'user_id' => $user->id, 'role' => 'Administrador', 'status' => 'active']);
            CompanyModule::create(['company_id' => $company->id, 'module_key' => 'services', 'enabled' => false, 'source' => 'company']);
            CompanySetting::create(['company_id' => $company->id, 'setting_key' => 'business.activity', 'setting_value' => ['value' => trim($data['activity'] ?? '')]]);

            $paidAt = $lockedIntent->paid_at ?? now();
            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $lockedIntent->plan_id,
                'provider' => 'mercado_pago',
                'provider_reference' => $lockedIntent->purchase_reference,
                'status' => 'active',
                'current_period_start' => $paidAt,
                'current_period_end' => $paidAt->copy()->addYear(),
                'metadata' => ['product_code' => (string) config('services.subscription_panel.product_code')],
            ]);
            $event = PaymentEvent::query()->where('provider', 'mercado_pago')
                ->where('provider_event_id', 'mp-payment-'.$lockedIntent->payment_id)->firstOrFail();
            $event->update([
                'subscription_id' => $subscription->id,
                'billing_stage' => 'initial',
                'expected_amount_cents' => $lockedIntent->expected_amount_cents,
                'paid_amount_cents' => $lockedIntent->expected_amount_cents,
                'status' => 'processed',
                'processed_at' => now(),
                'panel_sync_status' => 'pending',
            ]);
            $lockedIntent->update(['status' => 'registered', 'registered_at' => now()]);

            return [$user, $company, $event];
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('active_company_id', $company->id);
        $panelSync->send($event);

        if (in_array(config('mail.default'), ['smtp', 'ses', 'ses-v2', 'mailgun', 'postmark', 'resend', 'sendmail'], true)) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (Throwable $exception) {
                Log::warning('Paid registration verification email delivery failed.', ['exception_type' => get_class($exception)]);
            }
        }

        return redirect()->route('verification.notice')->with('status', 'Pagamento confirmado. Empresa cadastrada e assinatura ativada. Verifique seu e-mail para acessar o sistema.');
    }
}
