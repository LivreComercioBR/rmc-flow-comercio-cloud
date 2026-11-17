<?php

namespace App\Services\Subscriptions;

use App\Models\Plan;
use App\Models\Subscription;

final class PlanChangePricing
{
    public function upgradeAmountCents(Subscription $subscription, Plan $targetPlan): int
    {
        $currentPlan = $subscription->plan;
        if (! $currentPlan || ! $subscription->current_period_end?->isFuture()) return 0;

        $lastStage = $subscription->paymentEvents()
            ->where('status', 'processed')
            ->where('paid_amount_cents', '>', 0)
            ->latest('processed_at')
            ->value('billing_stage');
        $fullDifference = max(0, (int) ($lastStage === 'renewal'
            ? $targetPlan->renewal_price_cents - $currentPlan->renewal_price_cents
            : $targetPlan->initial_price_cents - $currentPlan->initial_price_cents));

        $periodStart = $subscription->current_period_start ?? now();
        $periodEnd = $subscription->current_period_end;
        $totalSeconds = max(1, $periodStart->diffInSeconds($periodEnd));
        $remainingSeconds = max(0, now()->diffInSeconds($periodEnd, false));

        return (int) round($fullDifference * min(1, $remainingSeconds / $totalSeconds));
    }
}
