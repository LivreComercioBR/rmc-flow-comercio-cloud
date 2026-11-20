<?php

namespace App\Services\MercadoPago;

use App\Models\LocalLicenseOrder;
use App\Models\PaymentEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class LocalLicensePayment
{
    public function record(array $payment, PaymentEvent $event, string $paymentId, string $reference): void
    {
        DB::transaction(function () use ($payment, $event, $paymentId, $reference): void {
            $order = LocalLicenseOrder::query()
                ->where('reference', substr($reference, strlen(LocalLicenseOrder::REFERENCE_PREFIX)))
                ->lockForUpdate()->first();
            $event = PaymentEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $providerStatus = (string) ($payment['status'] ?? 'unknown');
            $amount = (int) round(((float) ($payment['transaction_amount'] ?? 0)) * 100);

            $valid = $order
                && (string) ($payment['id'] ?? '') === $paymentId
                && ($payment['metadata']['product_code'] ?? null) === LocalLicenseOrder::PRODUCT
                && ($payment['currency_id'] ?? null) === 'BRL'
                && $amount === $order->amount_cents;

            $result = 'mismatch';
            if ($valid) {
                $result = $this->updateOrder($order, $payment, $paymentId, $providerStatus);
            }

            // Keep these events eligible for later notifications, including refunds.
            $event->update([
                'status' => 'observed',
                'purchase_reference' => $reference,
                'expected_amount_cents' => $order?->amount_cents,
                'paid_amount_cents' => $providerStatus === 'approved' ? $amount : 0,
                'processed_at' => now(),
                'payload' => [
                    'payment_id' => $paymentId,
                    'payment_status' => $providerStatus,
                    'local_license_result' => $result,
                ],
            ]);
        });
    }

    private function updateOrder(LocalLicenseOrder $order, array $payment, string $paymentId, string $status): string
    {
        if ($order->payment_id !== null && $order->payment_id !== $paymentId) {
            return $status === 'approved' ? 'duplicate_payment_review' : 'other_attempt';
        }

        if ($order->payment_id === null && ! in_array($status, ['approved', 'refunded', 'charged_back', 'in_mediation'], true)) {
            return 'awaiting_approval';
        }

        $updatedAt = isset($payment['date_last_updated'])
            ? Carbon::parse($payment['date_last_updated'])->setTimezone(config('app.timezone'))
            : null;
        if ($order->payment_updated_at && (! $updatedAt || $updatedAt->lt($order->payment_updated_at))) {
            return 'older_state';
        }

        $paid = $status === 'approved' && (float) ($payment['transaction_amount_refunded'] ?? 0) === 0.0;
        $order->update([
            'payment_id' => $paymentId,
            'status' => $paid ? 'paid' : 'review',
            'payment_updated_at' => $updatedAt ?? $order->payment_updated_at,
            'paid_at' => $status === 'approved' ? ($order->paid_at ?? now()) : $order->paid_at,
        ]);

        return $order->status;
    }
}

