<?php

namespace App\Services\SubscriptionPanel;

use App\Models\Subscription;
use App\Models\PaymentEvent;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class SubscriptionPanelClient
{
    public function sync(Subscription $subscription, PaymentEvent $event): bool
    {
        $url = rtrim((string) config('services.subscription_panel.url', ''), '/');
        $product = (string) config('services.subscription_panel.product_code', '');
        $secret = (string) config('services.subscription_panel.secret', '');
        if ($url === '' || $product === '' || $secret === '') return false;
        $latestPayment = $subscription->paymentEvents()
            ->where('status', 'processed')
            ->where('paid_amount_cents', '>', 0)
            ->latest('processed_at')
            ->first() ?? $event;
        $paymentId = (string) ($latestPayment->payload['payment_id'] ?? str_replace('mp-payment-', '', $latestPayment->provider_event_id));
        $paidAt = (string) ($latestPayment->payload['paid_at'] ?? now()->toIso8601String());
        $eventId = 'commerce-cloud:subscription:'.$subscription->id.':payment:'.$paymentId;
        $body = ['event_type' => 'subscription.snapshot', 'occurred_at' => $paidAt, 'subscription' => [
            'external_reference' => 'company:'.$subscription->company_id,
            'customer_name' => $subscription->company->trade_name,
            'customer_email' => $subscription->company->email,
            'customer_document' => $subscription->company->document,
            'plan_code' => $subscription->plan->code,
            'status' => $subscription->status,
            'starts_at' => $subscription->current_period_start?->toIso8601String(),
            'expires_at' => $subscription->current_period_end?->toIso8601String(),
            'initial_amount' => number_format($subscription->plan->initial_price_cents / 100, 2, '.', ''),
            'renewal_amount' => number_format($subscription->plan->renewal_price_cents / 100, 2, '.', ''),
            'currency' => 'BRL', 'provider' => 'mercado_pago', 'payment_status' => 'approved',
            'last_payment_reference' => $paymentId,
            'last_payment_amount' => number_format(((int) $latestPayment->paid_amount_cents) / 100, 2, '.', ''),
            'paid_at' => $paidAt,
        ]];
        $raw = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $manifest = $product."\n".$timestamp."\n".$eventId."\n".hash('sha256', $raw);
        $response = Http::acceptJson()->withHeaders([
            'X-RMC-Product-Code' => $product, 'X-RMC-Timestamp' => $timestamp,
            'X-RMC-Event-Id' => $eventId, 'X-RMC-Signature' => hash_hmac('sha256', $manifest, $secret),
        ])->timeout(12)->withBody($raw, 'application/json')->post($url.'/api/subscription-events.php');
        if (! $response->successful()) throw new RuntimeException('Subscription panel rejected snapshot: HTTP '.$response->status());
        return true;
    }
}
