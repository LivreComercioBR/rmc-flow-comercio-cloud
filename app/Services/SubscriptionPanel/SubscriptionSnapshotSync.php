<?php

namespace App\Services\SubscriptionPanel;

use App\Models\PaymentEvent;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SubscriptionSnapshotSync
{
    public function __construct(private readonly SubscriptionPanelClient $client) {}

    public function send(PaymentEvent $event): bool
    {
        $claimed = DB::transaction(function () use ($event): bool {
            $locked = PaymentEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'processed' || $locked->panel_sync_status === 'synced') {
                return false;
            }
            if ($locked->panel_sync_status === 'syncing' && $locked->updated_at?->gt(now()->subMinutes(5))) {
                return false;
            }

            $locked->update([
                'panel_sync_status' => 'syncing',
                'panel_sync_attempts' => $locked->panel_sync_attempts + 1,
                'panel_sync_error' => null,
            ]);

            return true;
        });

        if (! $claimed) {
            return $event->fresh()?->panel_sync_status === 'synced';
        }

        $event->refresh();

        try {
            $subscription = $event->subscription()->with(['company', 'plan'])->firstOrFail();
            $sent = $this->client->sync($subscription, $event);
            if (! $sent) {
                $event->update([
                    'panel_sync_status' => 'pending',
                    'panel_sync_error' => 'panel_configuration_incomplete',
                ]);
                return false;
            }

            $event->update([
                'panel_sync_status' => 'synced',
                'panel_synced_at' => now(),
                'panel_sync_error' => null,
            ]);

            return true;
        } catch (Throwable $exception) {
            $event->update([
                'panel_sync_status' => 'failed',
                'panel_sync_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
            report($exception);

            return false;
        }
    }
}
