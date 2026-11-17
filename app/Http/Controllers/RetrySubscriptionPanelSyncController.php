<?php

namespace App\Http\Controllers;

use App\Models\PaymentEvent;
use App\Services\SubscriptionPanel\SubscriptionSnapshotSync;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class RetrySubscriptionPanelSyncController extends Controller
{
    public function __invoke(Request $request, CompanyContext $context, SubscriptionSnapshotSync $sync): RedirectResponse
    {
        $company = $context->company();
        abort_unless($company, 403);
        $isAdministrator = $request->user()->companyMemberships()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->where('role', 'Administrador')
            ->exists();
        abort_unless($isAdministrator, 403);

        $event = PaymentEvent::query()
            ->whereHas('subscription', fn ($query) => $query->where('company_id', $company->id))
            ->where('status', 'processed')
            ->where(function ($query): void {
                $query->whereIn('panel_sync_status', ['pending', 'failed'])
                    ->orWhere(function ($staleSync): void {
                        $staleSync->where('panel_sync_status', 'syncing')
                            ->where('updated_at', '<=', now()->subMinutes(5));
                    });
            })
            ->latest('processed_at')
            ->first();

        if (! $event) {
            return redirect()->route('subscription.status')->with('panel_sync_notice', 'Não há sincronizações pendentes para reenviar.');
        }

        $synced = $sync->send($event);
        $configurationMissing = $event->fresh()?->panel_sync_error === 'panel_configuration_incomplete';

        return redirect()->route('subscription.status')->with(
            'panel_sync_notice',
            $synced
                ? 'Assinatura sincronizada com o painel central.'
                : ($configurationMissing
                    ? 'A integração com o painel central não está configurada. O pagamento e a assinatura continuam registrados neste sistema.'
                    : 'A sincronização não foi concluída. Confira a configuração e tente novamente.'),
        );
    }
}
