<?php

namespace App\Services\MercadoPago;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PaymentGateway
{
    public function createCheckout(array $payload): array
    {
        $token = (string) config('services.mercado_pago.access_token', '');
        if ($token === '') throw new RuntimeException('Mercado Pago access token is not configured.');
        $response = Http::acceptJson()->withToken($token)->timeout(12)
            ->post('https://api.mercadopago.com/checkout/preferences', $payload);
        if (! $response->successful()) throw new RuntimeException('Could not create Mercado Pago checkout: HTTP '.$response->status());
        return $response->json();
    }

    public function fetch(string $paymentId): array
    {
        $token = (string) config('services.mercado_pago.access_token', '');
        if ($token === '') throw new RuntimeException('Mercado Pago access token is not configured.');
        $response = Http::acceptJson()->withToken($token)->timeout(12)->get('https://api.mercadopago.com/v1/payments/'.rawurlencode($paymentId));
        if (! $response->successful()) throw new RuntimeException('Could not retrieve Mercado Pago payment: HTTP '.$response->status());
        return $response->json();
    }
}
