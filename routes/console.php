<?php

use App\Models\LocalLicenseOrder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mercado-pago:preflight', function () {
    $httpsUrl = static fn (mixed $value): bool => is_string($value)
        && filter_var($value, FILTER_VALIDATE_URL)
        && str_starts_with($value, 'https://');

    $checks = [
        'Ambiente sandbox ativo' => (bool) config('services.mercado_pago.sandbox'),
        'Checkout de homologacao habilitado' => (bool) config('services.mercado_pago.checkout_enabled'),
        'Access Token configurado' => filled(config('services.mercado_pago.access_token')),
        'Segredo do webhook configurado' => filled(config('services.mercado_pago.webhook_secret')),
        'APP_URL publico com HTTPS' => $httpsUrl(config('app.url')),
        'URL do webhook publica com HTTPS' => $httpsUrl(config('services.mercado_pago.notification_url')),
        'Codigo do produto configurado' => filled(config('services.subscription_panel.product_code')),
    ];

    foreach ($checks as $label => $passed) {
        $this->line(($passed ? '<info>[OK]</info> ' : '<error>[FALTA]</error> ').$label);
    }

    $panelUrl = config('services.subscription_panel.url');
    $panelSecret = config('services.subscription_panel.secret');
    $panelConfigured = filled($panelUrl) && filled($panelSecret);
    $panelPartiallyConfigured = filled($panelUrl) !== filled($panelSecret);
    if ($panelPartiallyConfigured || ($panelConfigured && ! $httpsUrl($panelUrl))) {
        $this->error('[FALTA] Integracao do painel central incompleta ou sem HTTPS.');
        $checks['Integracao do painel central consistente'] = false;
    } else {
        $this->line($panelConfigured
            ? '<info>[OK]</info> Integracao do painel central configurada'
            : '<comment>[OPCIONAL]</comment> Integracao do painel central ainda nao configurada');
    }

    $this->line('Ativacao automatica de pagamentos: '.(config('services.mercado_pago.activate_approved_payments') ? 'habilitada' : 'desabilitada'));
    $ready = ! in_array(false, $checks, true);
    $this->line($ready ? '<info>Configuracao pronta para iniciar homologacao.</info>' : '<comment>Corrija os itens marcados antes do teste.</comment>');

    return $ready ? 0 : 1;
})->purpose('Confere a configuracao sandbox sem exibir segredos ou iniciar cobrancas');

Artisan::command('comercio-local:pedido {referencia}', function () {
    $order = LocalLicenseOrder::query()->where('reference', $this->argument('referencia'))->first();
    if (! $order) {
        $this->error('Pedido não encontrado.');

        return 1;
    }

    $this->table(['Referência', 'E-mail da compra', 'Situação', 'Pagamento', 'Confirmação'], [[
        $order->reference,
        $order->contact_email,
        match ($order->status) {
            'paid' => 'Confirmado',
            'review' => 'Em análise',
            default => 'Pendente',
        },
        $order->payment_id ?? '—',
        $order->paid_at?->format('d/m/Y H:i') ?? '—',
    ]]);

    return 0;
})->purpose('Consulta o pagamento de uma licença do Comércio Local pela referência do pedido');
