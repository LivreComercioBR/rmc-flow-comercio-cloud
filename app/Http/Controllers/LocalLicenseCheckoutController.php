<?php

namespace App\Http\Controllers;

use App\Models\LocalLicenseOrder;
use App\Services\MercadoPago\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Throwable;

final class LocalLicenseCheckoutController extends Controller
{
    public function show(): Response
    {
        return response()->view('checkout.local-license', [
            'enabled' => config('services.mercado_pago.checkout_enabled'),
        ]);
    }

    public function start(Request $request, PaymentGateway $gateway): RedirectResponse
    {
        abort_unless(config('services.mercado_pago.checkout_enabled'), 503);

        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:190']]);
        $token = Str::random(64);
        $returnUrl = route('local-license.return', ['token' => $token]);
        $sandbox = (bool) config('services.mercado_pago.sandbox');
        $notificationUrl = (string) config('services.mercado_pago.notification_url');

        abort_unless(
            $this->isHttps($returnUrl)
                && ($sandbox || ($this->isHttps($notificationUrl) && filled(config('services.mercado_pago.webhook_secret')))),
            503
        );

        $order = LocalLicenseOrder::create([
            'reference' => (string) Str::uuid(),
            'token' => hash('sha256', $token),
            'contact_email' => mb_strtolower(trim($data['email'])),
            'amount_cents' => LocalLicenseOrder::PRICE_CENTS,
            'status' => 'pending',
        ]);

        $payload = [
            'items' => [[
                'id' => LocalLicenseOrder::PRODUCT,
                'title' => 'RMC Flow Comércio Local',
                'description' => 'Licença permanente para um computador Windows. Pagamento único.',
                'quantity' => 1,
                'currency_id' => 'BRL',
                'unit_price' => $order->amount_cents / 100,
            ]],
            'payer' => ['email' => $order->contact_email],
            'external_reference' => $order->paymentReference(),
            'metadata' => ['product_code' => LocalLicenseOrder::PRODUCT],
            'back_urls' => array_fill_keys(['success', 'pending', 'failure'], $returnUrl),
            'auto_return' => 'approved',
            'expires' => true,
            'expiration_date_from' => now()->toIso8601String(),
            'expiration_date_to' => now()->addDay()->toIso8601String(),
        ];

        if (! $sandbox) {
            $payload['notification_url'] = $notificationUrl;
        }

        try {
            $checkout = $gateway->createCheckout($payload);
            $url = $checkout[$sandbox ? 'sandbox_init_point' : 'init_point'] ?? null;
            if (is_string($url) && $this->isHttps($url)
                && preg_match('/(^|\.)mercadopago\.(com\.br|com)$/i', (string) parse_url($url, PHP_URL_HOST))) {
                return redirect()->away($url);
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return redirect()->route('local-license.show')->withInput()->withErrors([
            'checkout' => 'Não foi possível abrir o Mercado Pago. Tente novamente em alguns minutos.',
        ]);
    }

    public function returned(string $token): Response
    {
        $order = LocalLicenseOrder::query()->where('token', hash('sha256', $token))->firstOrFail();
        $supportUrl = 'https://wa.me/5519990136498?text='.rawurlencode(
            'Olá! Preciso de ajuda com meu pedido do RMC Flow Comércio Local. Referência: '.$order->reference
        );

        return response()->view('checkout.local-license-return', compact('order', 'supportUrl'))
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    private function isHttps(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($url, 'https://');
    }
}

