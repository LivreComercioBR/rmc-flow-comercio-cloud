<?php

namespace App\Http\Controllers;

use App\Models\PaymentEvent;
use App\Models\LocalLicenseOrder;
use App\Models\Subscription;
use App\Models\SubscriptionCheckoutIntent;
use App\Services\MercadoPago\MercadoPagoSignature;
use App\Services\MercadoPago\LocalLicensePayment;
use App\Services\MercadoPago\PaymentGateway;
use App\Services\SubscriptionPanel\SubscriptionSnapshotSync;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class MercadoPagoWebhookController extends Controller
{
    public function __invoke(Request $request, MercadoPagoSignature $signature, PaymentGateway $gateway, SubscriptionSnapshotSync $panelSync): JsonResponse
    {
        $dataId = (string) $request->input('data.id', $request->query('data.id', ''));
        $topic = (string) $request->input('type', $request->query('type', ''));
        $requestId = (string) $request->header('x-request-id', '');
        $notificationId = (string) $request->input('id', $request->query('id', ''));
        // Mercado Pago may send several distinct notifications for one payment.
        // The payment ID is the stable idempotency key for financial processing.
        $eventId = $dataId !== '' ? 'mp-payment-'.$dataId : $notificationId;
        if (! $signature->valid($request, $dataId)) {
            Log::warning('Mercado Pago webhook signature rejected.', ['request_id' => $requestId]);
            return response()->json(['accepted' => false], 401);
        }
        if ($topic !== 'payment' || $dataId === '' || $eventId === '') return response()->json(['accepted' => true, 'ignored' => true]);

        try {
            $event = PaymentEvent::firstOrCreate(['provider' => 'mercado_pago', 'provider_event_id' => $eventId], [
                'event_type' => $topic, 'status' => 'received', 'received_at' => now(),
                'payload' => ['payment_id' => $dataId, 'notification_id' => $notificationId, 'request_id' => $requestId],
            ]);
        } catch (QueryException $exception) {
            $event = PaymentEvent::where('provider', 'mercado_pago')->where('provider_event_id', $eventId)->first();
            if (! $event) throw $exception;
        }
        $activationEnabled = (bool) config('services.mercado_pago.activate_approved_payments', false);
        $reprocessableStatuses = ['received', 'failed', 'observed', 'ignored'];
        if ($activationEnabled) {
            $reprocessableStatuses[] = 'awaiting_billing_policy';
        }
        if (! $event->wasRecentlyCreated && ! in_array($event->status, [...$reprocessableStatuses, 'processed'], true)) {
            return response()->json(['accepted' => true, 'duplicate' => true]);
        }

        // A processed payment is never applied twice. A repeated webhook may only
        // resume its independent synchronization with the central subscription panel.
        if ($event->status === 'processed') {
            $panelSync->send($event);
            return response()->json(['accepted' => true, 'duplicate' => true]);
        }

        try {
            $payment = $gateway->fetch($dataId);
            $reference = (string) ($payment['external_reference'] ?? '');
            if (str_starts_with($reference, LocalLicenseOrder::REFERENCE_PREFIX)) {
                app(LocalLicensePayment::class)->record($payment, $event, $dataId, $reference);

                return response()->json(['accepted' => true]);
            }
            if (str_starts_with($reference, 'purchase:')) {
                return $this->processInitialPurchase($payment, $event, $dataId, $reference);
            }
            $paymentMetadata = is_array($payment['metadata'] ?? null) ? $payment['metadata'] : [];
            $subscriptionId = (int) ($paymentMetadata['subscription_id'] ?? 0);
            $companyId = (int) ($paymentMetadata['company_id'] ?? 0);
            $subscription = null;

            if ($subscriptionId > 0 && $companyId > 0) {
                $subscription = Subscription::query()
                    ->whereKey($subscriptionId)
                    ->where('company_id', $companyId)
                    ->where('provider', 'mercado_pago')
                    ->first();
            }

            // Older subscriptions may store a purchase reference instead of company:{id}.
            // The authenticated payment metadata identifies the stable subscription record.
            if (! $subscription && $reference !== '') {
                $subscription = Subscription::where('provider', 'mercado_pago')
                    ->where('provider_reference', $reference)
                    ->first();
            }

            if (! $subscription) {
                $event->update([
                    'status' => 'ignored',
                    'processed_at' => now(),
                    'payload' => [
                        'payment_id' => $dataId,
                        'reason' => 'No Commerce Cloud subscription matched the payment metadata or external reference.',
                    ],
                ]);
                return response()->json(['accepted' => true, 'ignored' => true]);
            }
            $expectedProductCode = (string) config('services.subscription_panel.product_code', '');
            if ($expectedProductCode === ''
                || (string) ($paymentMetadata['product_code'] ?? '') !== $expectedProductCode
                || (int) ($paymentMetadata['subscription_id'] ?? 0) !== (int) $subscription->id
                || (int) ($paymentMetadata['company_id'] ?? 0) !== (int) $subscription->company_id) {
                $event->update(['status' => 'ignored', 'processed_at' => now(), 'payload' => [
                    'payment_id' => $dataId,
                    'reason' => 'Payment metadata does not identify this Commerce Cloud subscription.',
                ]]);
                return response()->json(['accepted' => true, 'ignored' => true]);
            }
            $outcome = DB::transaction(function () use ($subscription, $event, $eventId, $dataId, $payment): string {
                $lockedSubscription = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->with('plan')->firstOrFail();
                $lockedEvent = PaymentEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
                $reprocessableStatuses = ['received', 'failed', 'observed', 'ignored'];
                if (config('services.mercado_pago.activate_approved_payments', false)) {
                    $reprocessableStatuses[] = 'awaiting_billing_policy';
                }
                if (! in_array($lockedEvent->status, $reprocessableStatuses, true)) {
                    return in_array($lockedEvent->status, ['processed', 'awaiting_billing_policy'], true)
                        ? 'duplicate'
                        : 'ignored';
                }

                $hasPriorApprovedPayment = $lockedSubscription->paymentEvents()
                    ->where('provider_event_id', '!=', $eventId)
                    ->whereIn('status', ['processed', 'awaiting_billing_policy'])
                    ->where('paid_amount_cents', '>', 0)
                    ->exists();
                $paymentMetadata = is_array($payment['metadata'] ?? null) ? $payment['metadata'] : [];
                $isUpgrade = ($paymentMetadata['billing_stage'] ?? null) === 'upgrade';
                $pendingCheckout = ($lockedSubscription->metadata ?? [])['pending_checkout'] ?? [];
                $hasPendingCheckout = is_array($pendingCheckout) && ! empty($pendingCheckout['purchase_reference']);
                $checkoutMatches = is_array($pendingCheckout)
                    && $hasPendingCheckout
                    && hash_equals((string) $pendingCheckout['purchase_reference'], (string) ($paymentMetadata['purchase_reference'] ?? ''))
                    && hash_equals((string) ($pendingCheckout['billing_stage'] ?? ''), (string) ($paymentMetadata['billing_stage'] ?? ''))
                    && hash_equals((string) ($pendingCheckout['plan_code'] ?? ''), (string) ($paymentMetadata['plan_code'] ?? ''))
                    && (int) ($pendingCheckout['expected_amount_cents'] ?? 0) === (int) ($paymentMetadata['expected_amount_cents'] ?? 0);
                $pendingOrderMismatch = $hasPendingCheckout && ! $checkoutMatches;
                $pendingUpgrade = ($lockedSubscription->metadata ?? [])['pending_upgrade'] ?? [];
                $upgradeMatches = $isUpgrade
                    && $checkoutMatches
                    && is_array($pendingUpgrade)
                    && hash_equals((string) ($pendingUpgrade['purchase_reference'] ?? ''), (string) ($paymentMetadata['purchase_reference'] ?? ''))
                    && hash_equals((string) ($pendingUpgrade['plan_code'] ?? ''), (string) ($paymentMetadata['plan_code'] ?? ''))
                    && (int) ($pendingUpgrade['expected_amount_cents'] ?? 0) === (int) ($paymentMetadata['expected_amount_cents'] ?? 0);
                $targetPlan = $upgradeMatches ? \App\Models\Plan::query()->whereKey((int) $pendingUpgrade['plan_id'])->where('active', true)->first() : null;
                $orderPlan = $checkoutMatches ? \App\Models\Plan::query()->whereKey((int) $pendingCheckout['plan_id'])->where('active', true)->first() : null;
                $billingStage = $checkoutMatches ? (string) $pendingCheckout['billing_stage'] : ($hasPriorApprovedPayment ? 'renewal' : 'initial');
                $isRenewal = $billingStage === 'renewal';
                $scheduledPlanId = ($lockedSubscription->metadata ?? [])['scheduled_plan_change']['plan_id'] ?? null;
                $renewalPlan = $isRenewal ? ($orderPlan ?? ($scheduledPlanId ? \App\Models\Plan::query()->whereKey($scheduledPlanId)->where('active', true)->first() : null)) : null;
                $expectedRenewalPlanId = $scheduledPlanId !== null ? (int) $scheduledPlanId : (int) $lockedSubscription->plan_id;
                $renewalPlanMismatch = $isRenewal
                    && (! $orderPlan || (int) $orderPlan->id !== $expectedRenewalPlanId);
                $expectedAmount = $isUpgrade && $targetPlan
                    ? (int) $pendingUpgrade['expected_amount_cents']
                    : ($checkoutMatches ? (int) $pendingCheckout['expected_amount_cents'] : ($isRenewal ? ($renewalPlan?->renewal_price_cents ?? $lockedSubscription->plan->renewal_price_cents) : $lockedSubscription->plan->initial_price_cents));
                $paidAmount = (int) round(((float) ($payment['transaction_amount'] ?? 0)) * 100);
                $purchaseReference = (string) ($paymentMetadata['purchase_reference'] ?? '');
                $eventData = [
                    'subscription_id' => $lockedSubscription->id,
                    'purchase_reference' => $purchaseReference !== '' ? $purchaseReference : null,
                    'billing_stage' => $isUpgrade && $targetPlan ? 'upgrade' : $billingStage,
                    'expected_amount_cents' => $expectedAmount,
                    'paid_amount_cents' => $paidAmount,
                ];

                $orderAlreadyPaid = ($payment['status'] ?? null) === 'approved'
                    && $paidAmount > 0
                    && $purchaseReference !== ''
                    && $lockedSubscription->paymentEvents()
                        ->where('provider_event_id', '!=', $eventId)
                        ->where('purchase_reference', $purchaseReference)
                        ->whereIn('status', ['processed', 'awaiting_billing_policy'])
                        ->where('paid_amount_cents', '>', 0)
                        ->exists();
                if ($orderAlreadyPaid) {
                    $lockedEvent->update($eventData + [
                        'status' => 'duplicate_order',
                        'processed_at' => now(),
                        'processing_error' => 'A different approved payment already consumed this checkout reference. Review the duplicate payment for refund.',
                        'payload' => ['payment_id' => $dataId, 'payment_status' => 'approved', 'duplicate_order' => true],
                    ]);
                    return 'duplicate_order';
                }

                if ($pendingOrderMismatch || ($payment['currency_id'] ?? null) !== 'BRL' || ($isUpgrade && ! $targetPlan) || $renewalPlanMismatch || $paidAmount !== (int) $expectedAmount) {
                    $lockedEvent->update($eventData + [
                        'status' => 'rejected',
                        'processed_at' => now(),
                        'processing_error' => $pendingOrderMismatch
                            ? 'Payment metadata does not match the pending checkout order.'
                            : ($renewalPlanMismatch
                                ? 'Renewal checkout plan does not match the current or scheduled subscription plan.'
                                : 'Payment amount or currency does not match the expected billing stage.'),
                    ]);
                    return 'rejected';
                }

                if (($payment['status'] ?? null) !== 'approved') {
                    $lockedEvent->update($eventData + [
                        'status' => 'observed', 'processed_at' => now(),
                        'payload' => ['payment_id' => $dataId, 'payment_status' => $payment['status'] ?? 'unknown'],
                    ]);
                    return 'observed';
                }

                if (! config('services.mercado_pago.activate_approved_payments', false)) {
                    $lockedEvent->update($eventData + [
                        'status' => 'awaiting_billing_policy', 'processed_at' => now(),
                        'payload' => ['payment_id' => $dataId, 'payment_status' => 'approved'],
                    ]);
                    return 'awaiting_billing_policy';
                }

                $lockedEvent->update($eventData + [
                    'status' => 'awaiting_billing_policy',
                    'payload' => [
                        'payment_id' => $dataId,
                        'payment_status' => 'approved',
                        'paid_at' => $payment['date_approved'] ?? now()->toIso8601String(),
                    ],
                ]);

                $paidAt = isset($payment['date_approved']) ? \Illuminate\Support\Carbon::parse($payment['date_approved']) : now();
                $previousPeriodEnd = $lockedSubscription->current_period_end;
                $start = $previousPeriodEnd && $previousPeriodEnd->greaterThan($paidAt)
                    ? $previousPeriodEnd->copy()
                    : $paidAt->copy();
                $subscriptionChanges = ['status' => 'active'];
                if ($isUpgrade && $targetPlan) {
                    $subscriptionChanges['plan_id'] = $targetPlan->id;
                    $subscriptionChanges['current_period_start'] = $lockedSubscription->current_period_start ?? $paidAt;
                    $subscriptionChanges['current_period_end'] = $lockedSubscription->current_period_end;
                    $metadata = $lockedSubscription->metadata ?? [];
                    unset($metadata['scheduled_plan_change']);
                    unset($metadata['pending_upgrade']);
                    unset($metadata['pending_checkout']);
                    $subscriptionChanges['metadata'] = $metadata;
                } else {
                    if ($isRenewal && $scheduledPlanId !== null) $subscriptionChanges['plan_id'] = (int) $scheduledPlanId;
                    $subscriptionChanges['current_period_start'] = $isRenewal ? $start : ($lockedSubscription->current_period_start ?? $paidAt);
                    $subscriptionChanges['current_period_end'] = $start->copy()->addYear();
                    $subscriptionMetadata = $lockedSubscription->metadata ?? [];
                    unset($subscriptionMetadata['scheduled_plan_change']);
                    if ($checkoutMatches) unset($subscriptionMetadata['pending_checkout']);
                    $subscriptionChanges['metadata'] = $subscriptionMetadata;
                }
                $lockedSubscription->update($subscriptionChanges);
                $lockedEvent->update($eventData + [
                    'status' => 'processed', 'processed_at' => now(),
                    'panel_sync_status' => 'pending',
                    'payload' => [
                        'payment_id' => $dataId,
                        'payment_status' => 'approved',
                        'paid_at' => $payment['date_approved'] ?? now()->toIso8601String(),
                    ],
                ]);

                return 'processed';
            });

            if ($outcome === 'duplicate') {
                if ($event->fresh()?->status === 'processed') {
                    $panelSync->send($event->fresh());
                }
                return response()->json(['accepted' => true, 'duplicate' => true]);
            }
            if ($outcome === 'ignored') {
                return response()->json(['accepted' => true, 'ignored' => true]);
            }
            if ($outcome === 'rejected') {
                return response()->json(['accepted' => true, 'rejected' => true]);
            }
            if ($outcome === 'duplicate_order') {
                Log::warning('Duplicate approved Mercado Pago payment for an already-paid checkout reference.', ['payment_id' => $dataId, 'subscription_id' => $subscription->id]);
                return response()->json(['accepted' => true, 'duplicate_order' => true]);
            }
            if ($outcome === 'observed') {
                return response()->json(['accepted' => true]);
            }
            if ($outcome === 'awaiting_billing_policy') {
                return response()->json(['accepted' => true, 'activation_pending_configuration' => true]);
            }

            $panelSync->send($event);
        } catch (Throwable $exception) {
            if ($event->fresh()?->status !== 'processed') {
                $event->update(['status' => 'failed', 'processing_error' => mb_substr($exception->getMessage(), 0, 1000)]);
            }
            report($exception);
            return response()->json(['accepted' => false], 500);
        }
        return response()->json(['accepted' => true]);
    }

    private function processInitialPurchase(array $payment, PaymentEvent $event, string $paymentId, string $reference): JsonResponse
    {
        $intent = SubscriptionCheckoutIntent::query()->with('plan')->where('purchase_reference', $reference)->first();
        if (! $intent) {
            $event->update(['status' => 'ignored', 'processed_at' => now()]);
            return response()->json(['accepted' => true, 'ignored' => true]);
        }

        $metadata = is_array($payment['metadata'] ?? null) ? $payment['metadata'] : [];
        $productCode = (string) config('services.subscription_panel.product_code', '');
        $metadataMatches = $productCode !== ''
            && (string) ($metadata['product_code'] ?? '') === $productCode
            && (int) ($metadata['checkout_intent_id'] ?? 0) === (int) $intent->id
            && (string) ($metadata['plan_code'] ?? '') === (string) $intent->plan->code
            && (string) ($metadata['purchase_reference'] ?? '') === $intent->purchase_reference
            && (int) ($metadata['expected_amount_cents'] ?? 0) === (int) $intent->expected_amount_cents;
        $paidAmount = (int) round(((float) ($payment['transaction_amount'] ?? 0)) * 100);
        $validAmount = ($payment['currency_id'] ?? null) === 'BRL' && $paidAmount === (int) $intent->expected_amount_cents;
        if (! $metadataMatches || (($payment['status'] ?? null) === 'approved' && ! $validAmount)) {
            $event->update([
                'status' => 'rejected',
                'processed_at' => now(),
                'expected_amount_cents' => $intent->expected_amount_cents,
                'paid_amount_cents' => $paidAmount,
                'processing_error' => ! $metadataMatches ? 'Payment metadata does not match the pending purchase.' : 'Payment amount or currency does not match the selected plan.',
                'payload' => ['payment_id' => $paymentId, 'payment_status' => $payment['status'] ?? 'unknown'],
            ]);
            $intent->update(['status' => 'review_required', 'payment_id' => $paymentId, 'payment_status' => $payment['status'] ?? 'unknown']);
            return response()->json(['accepted' => true, 'rejected' => true]);
        }

        $outcome = DB::transaction(function () use ($event, $intent, $payment, $paymentId, $paidAmount): string {
            $lockedIntent = SubscriptionCheckoutIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();
            $lockedEvent = PaymentEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($lockedIntent->registered_at || $lockedEvent->status === 'processed') return 'duplicate';

            $status = (string) ($payment['status'] ?? 'unknown');
            $base = [
                'purchase_reference' => $lockedIntent->purchase_reference,
                'billing_stage' => 'initial',
                'expected_amount_cents' => $lockedIntent->expected_amount_cents,
                'paid_amount_cents' => $paidAmount,
                'processed_at' => now(),
                'payload' => ['payment_id' => $paymentId, 'payment_status' => $status],
            ];
            if ($status === 'approved' && ! config('services.mercado_pago.activate_approved_payments', false)) {
                $lockedIntent->update(['status' => 'awaiting_activation_policy', 'payment_id' => $paymentId, 'payment_status' => $status, 'paid_at' => $payment['date_approved'] ?? now()]);
                $lockedEvent->update($base + ['status' => 'awaiting_billing_policy']);
                return 'policy';
            }
            if ($status === 'approved') {
                $lockedIntent->update(['status' => 'approved', 'payment_id' => $paymentId, 'payment_status' => $status, 'paid_at' => $payment['date_approved'] ?? now()]);
                $lockedEvent->update($base + ['status' => 'awaiting_registration']);
                return 'approved';
            }

            $pending = in_array($status, ['pending', 'in_process', 'authorized'], true);
            $lockedIntent->update([
                'status' => $pending ? 'pending' : 'not_approved',
                'payment_id' => $paymentId,
                'payment_status' => $status,
            ]);
            $lockedEvent->update($base + ['status' => 'observed']);
            return 'not_approved';
        });

        return match ($outcome) {
            'approved' => response()->json(['accepted' => true, 'registration_available' => true]),
            'policy' => response()->json(['accepted' => true, 'activation_pending_configuration' => true]),
            'duplicate' => response()->json(['accepted' => true, 'duplicate' => true]),
            default => response()->json(['accepted' => true]),
        };
    }
}
