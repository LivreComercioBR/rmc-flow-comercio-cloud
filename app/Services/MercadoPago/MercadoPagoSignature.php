<?php

namespace App\Services\MercadoPago;

use Illuminate\Http\Request;

final class MercadoPagoSignature
{
    public function valid(Request $request, string $dataId): bool
    {
        $secret = (string) config('services.mercado_pago.webhook_secret', '');
        $header = (string) $request->header('x-signature', '');
        $requestId = (string) $request->header('x-request-id', '');
        if ($secret === '' || $header === '' || $requestId === '' || $dataId === '') return false;
        $parts = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key !== '') $parts[$key] = $value;
        }
        if (empty($parts['ts']) || ! ctype_digit($parts['ts']) || empty($parts['v1'])) return false;
        if (! preg_match('/^[a-f0-9]{64}$/i', $parts['v1'])) return false;
        $manifest = 'id:'.strtolower($dataId).';request-id:'.$requestId.';ts:'.$parts['ts'].';';
        return hash_equals(hash_hmac('sha256', $manifest, $secret), $parts['v1']);
    }
}
