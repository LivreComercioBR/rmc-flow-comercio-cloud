<?php

use App\Http\Controllers\CompanySelectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SubscriptionStatusController;
use App\Http\Controllers\MercadoPagoWebhookController;
use App\Http\Controllers\SubscriptionCheckoutController;
use App\Http\Controllers\SubscriptionPurchaseController;
use App\Http\Controllers\RetrySubscriptionPanelSyncController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DebtorController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PayableController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\FinancialSummaryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\CompanyModuleController;
use App\Http\Controllers\CompanyUserController;
use App\Http\Controllers\CompanyAuditController;
use App\Http\Controllers\CompanyInvitationController;
use App\Http\Controllers\ServiceCatalogController;
use App\Http\Controllers\ServiceAppointmentController;
use App\Http\Controllers\ServiceQuoteController;
use App\Http\Controllers\ServiceWorkOrderController;
use App\Http\Controllers\ServiceAssetController;
use App\Http\Controllers\Auth\CompanyRegistrationController;
use App\Http\Controllers\Auth\ChangeUnverifiedEmailController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Middleware\EnsureCompanyContextForBilling;
use Illuminate\Support\Facades\Route;

Route::get('/', [SubscriptionPurchaseController::class, 'plans'])->name('checkout.plans');
Route::post('/mercado-pago/webhook', MercadoPagoWebhookController::class)->middleware('throttle:120,1')->name('mercado-pago.webhook');
Route::get('/convites/{token}', [CompanyInvitationController::class, 'show'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:20,1')->name('company-invitations.show');
Route::post('/convites/{token}', [CompanyInvitationController::class, 'accept'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:5,1')->name('company-invitations.accept');
Route::post('/contratar', [SubscriptionPurchaseController::class, 'start'])->middleware('throttle:5,1')->name('checkout.start');
Route::get('/checkout/{token}/retorno', [SubscriptionPurchaseController::class, 'returned'])->where('token', '[A-Za-z0-9]{64}')->name('checkout.return');
Route::get('/checkout/{token}/cadastro', [SubscriptionPurchaseController::class, 'registration'])->where('token', '[A-Za-z0-9]{64}')->name('checkout.register');
Route::post('/checkout/{token}/cadastro', [SubscriptionPurchaseController::class, 'complete'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:5,1')->name('checkout.register.complete');

Route::middleware('guest')->group(function (): void {
    Route::redirect('/register', '/')->name('register');
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
    Route::get('/senha/esqueci', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/senha/esqueci', [PasswordResetController::class, 'sendLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/senha/redefinir/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/senha/redefinir', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/email/verificar', function (\Illuminate\Http\Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('subscription.status');
        }

        return view('auth.verify-email');
    })->name('verification.notice');
    Route::post('/email/verificar/notificacao', function (\Illuminate\Http\Request $request) {
        if ($request->user()->hasVerifiedEmail()) return redirect()->intended(route('dashboard'));
        if (! in_array(config('mail.default'), ['smtp', 'ses', 'ses-v2', 'mailgun', 'postmark', 'resend', 'sendmail'], true)) {
            return back()->withErrors(['email' => "O envio de e-mail ainda n\u{00E3}o est\u{00E1} configurado. Entre em contato com o suporte."]);
        }
        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::warning('Verification email delivery failed.', [
    'exception_type' => get_class($exception),
    'message' => $exception->getMessage(),]);
            return back()->withErrors(['email' => 'N\u{00E3}o foi poss\u{00ED}vel enviar o e-mail agora. Tente novamente mais tarde.']);
        }
        return back()->with('status', "Enviamos um novo link de verifica\u{00E7}\u{00E3}o.");
    })->middleware('throttle:3,1')->name('verification.send');
    Route::post('/email/verificar/alterar', ChangeUnverifiedEmailController::class)
        ->middleware('throttle:3,1')
        ->name('verification.email.update');
    Route::get('/email/verificar/{id}/{hash}', function (EmailVerificationRequest $request) {
        if ($request->user()->hasVerifiedEmail()) return redirect()->intended(route('dashboard'));
        $request->fulfill();
        return redirect()->intended(route('subscription.status'))->with('status', 'E-mail confirmado com sucesso.');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/empresas', [CompanySelectionController::class, 'index'])->name('companies.index');
    Route::post('/empresas/{company}/ativar', [CompanySelectionController::class, 'activate'])->whereNumber('company')->name('companies.activate');
    Route::get('/assinatura', SubscriptionStatusController::class)->middleware(EnsureCompanyContextForBilling::class)->name('subscription.status');
    Route::post('/assinatura/contratar', SubscriptionCheckoutController::class)->middleware(['verified', EnsureCompanyContextForBilling::class, 'throttle:5,1'])->name('subscription.checkout');
    Route::post('/assinatura/rebaixar', [SubscriptionCheckoutController::class, 'scheduleDowngrade'])->middleware(['verified', EnsureCompanyContextForBilling::class, 'throttle:5,1'])->name('subscription.downgrade');
    Route::delete('/assinatura/rebaixar', [SubscriptionCheckoutController::class, 'cancelDowngrade'])->middleware(['verified', EnsureCompanyContextForBilling::class, 'throttle:5,1'])->name('subscription.downgrade.cancel');
    Route::post('/assinatura/sincronizar-painel', RetrySubscriptionPanelSyncController::class)->middleware(['verified', EnsureCompanyContextForBilling::class, 'throttle:3,1'])->name('subscription.panel-sync.retry');

    Route::middleware(['verified', 'company.context', 'company.role'])->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::post('/dashboard/alternar-valores', [DashboardController::class, 'toggleValues'])->name('dashboard.values.toggle');
        Route::get('/configuracoes/empresa', [CompanySettingsController::class, 'edit'])->name('company.settings.edit');
        Route::put('/configuracoes/empresa', [CompanySettingsController::class, 'update'])->name('company.settings.update');
        Route::put('/configuracoes/modulos', [CompanyModuleController::class, 'update'])->name('company.modules.update');
        Route::get('/configuracoes/usuarios', [CompanyUserController::class, 'index'])->name('company.users.index');
        Route::patch('/configuracoes/usuarios/{membership}', [CompanyUserController::class, 'update'])->whereNumber('membership')->name('company.users.update');
        Route::post('/configuracoes/usuarios/convites', [CompanyInvitationController::class, 'store'])->middleware('throttle:5,1')->name('company.invitations.store');
        Route::patch('/configuracoes/usuarios/convites/{invitation}/revogar', [CompanyInvitationController::class, 'revoke'])->whereNumber('invitation')->name('company.invitations.revoke');
        Route::get('/configuracoes/auditoria', [CompanyAuditController::class, 'index'])->name('company.audit.index');
        Route::get('/clientes', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/devedores', [DebtorController::class, 'index'])->name('debtors.index');
        Route::get('/clientes/novo', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/clientes', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/clientes/{customer}', [CustomerController::class, 'show'])->whereNumber('customer')->name('customers.show');
        Route::get('/clientes/{customer}/editar', [CustomerController::class, 'edit'])->whereNumber('customer')->name('customers.edit');
        Route::put('/clientes/{customer}', [CustomerController::class, 'update'])->whereNumber('customer')->name('customers.update');
        Route::patch('/clientes/{customer}/desativar', [CustomerController::class, 'deactivate'])->whereNumber('customer')->name('customers.deactivate');
        Route::patch('/clientes/{customer}/ativar', [CustomerController::class, 'activate'])->whereNumber('customer')->name('customers.activate');
        Route::get('/categorias', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categorias', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categorias/{category}', [CategoryController::class, 'update'])->whereNumber('category')->name('categories.update');
        Route::patch('/categorias/{category}/status', [CategoryController::class, 'toggle'])->whereNumber('category')->name('categories.toggle');
        Route::get('/produtos', [ProductController::class, 'index'])->name('products.index');
        Route::get('/produtos/novo', [ProductController::class, 'create'])->name('products.create');
        Route::post('/produtos', [ProductController::class, 'store'])->name('products.store');
        Route::get('/produtos/{product}/editar', [ProductController::class, 'edit'])->whereNumber('product')->name('products.edit');
        Route::put('/produtos/{product}', [ProductController::class, 'update'])->whereNumber('product')->name('products.update');
        Route::patch('/produtos/{product}/status', [ProductController::class, 'toggle'])->whereNumber('product')->name('products.toggle');
        Route::get('/produtos/{product}/estoque', [StockController::class, 'show'])->whereNumber('product')->name('products.stock');
        Route::post('/produtos/{product}/estoque', [StockController::class, 'store'])->whereNumber('product')->name('products.stock.store');
        Route::get('/fornecedores', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/fornecedores/novo', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('/fornecedores', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('/fornecedores/{supplier}', [SupplierController::class, 'show'])->whereNumber('supplier')->name('suppliers.show');
        Route::get('/fornecedores/{supplier}/editar', [SupplierController::class, 'edit'])->whereNumber('supplier')->name('suppliers.edit');
        Route::put('/fornecedores/{supplier}', [SupplierController::class, 'update'])->whereNumber('supplier')->name('suppliers.update');
        Route::patch('/fornecedores/{supplier}/status', [SupplierController::class, 'toggle'])->whereNumber('supplier')->name('suppliers.toggle');
        Route::get('/compras', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('/compras/nova', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('/compras', [PurchaseController::class, 'store'])->middleware('throttle:20,1')->name('purchases.store');
        Route::get('/compras/{purchase}', [PurchaseController::class, 'show'])->whereNumber('purchase')->name('purchases.show');
        Route::patch('/compras/{purchase}/cancelar', [PurchaseController::class, 'cancel'])->whereNumber('purchase')->middleware('throttle:5,1')->name('purchases.cancel');
        Route::get('/financeiro/contas-a-pagar', [PayableController::class, 'index'])->name('payables.index');
        Route::get('/financeiro/contas-a-pagar/nova', [PayableController::class, 'create'])->name('payables.manual.create');
        Route::post('/financeiro/contas-a-pagar', [PayableController::class, 'storeManual'])->middleware('throttle:10,1')->name('payables.manual.store');
        Route::post('/financeiro/contas-a-pagar/{payable}/pagamentos', [PayableController::class, 'pay'])->whereNumber('payable')->middleware('throttle:20,1')->name('payables.pay');
        Route::get('/caixa', [CashRegisterController::class, 'index'])->name('cash.index');
        Route::post('/caixa/abrir', [CashRegisterController::class, 'open'])->middleware('throttle:5,1')->name('cash.open');
        Route::get('/caixa/{session}', [CashRegisterController::class, 'show'])->whereNumber('session')->name('cash.show');
        Route::post('/caixa/{session}/movimentacoes', [CashRegisterController::class, 'movement'])->whereNumber('session')->middleware('throttle:20,1')->name('cash.movement');
        Route::post('/caixa/{session}/fechar', [CashRegisterController::class, 'close'])->whereNumber('session')->middleware('throttle:5,1')->name('cash.close');
        Route::get('/pdv', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/pdv/nova', [SaleController::class, 'create'])->name('sales.create');
        Route::post('/pdv', [SaleController::class, 'store'])->middleware('throttle:20,1')->name('sales.store');
        Route::get('/pdv/{sale}', [SaleController::class, 'show'])->whereNumber('sale')->name('sales.show');
        Route::patch('/pdv/{sale}/cancelar', [SaleController::class, 'cancel'])->whereNumber('sale')->middleware('throttle:5,1')->name('sales.cancel');
        Route::post('/pdv/{sale}/devolucoes', [SaleController::class, 'refund'])->whereNumber('sale')->middleware('throttle:10,1')->name('sales.refund');
        Route::get('/financeiro/contas-a-receber', [ReceivableController::class, 'index'])->name('receivables.index');
        Route::get('/financeiro/contas-a-receber/nova', [ReceivableController::class, 'create'])->name('receivables.manual.create');
        Route::post('/financeiro/contas-a-receber', [ReceivableController::class, 'storeManual'])->middleware('throttle:10,1')->name('receivables.manual.store');
        Route::post('/financeiro/contas-a-receber/{receivable}/pagamentos', [ReceivableController::class, 'pay'])->whereNumber('receivable')->middleware('throttle:20,1')->name('receivables.pay');
        Route::get('/financeiro/resumo', FinancialSummaryController::class)->name('financial.summary');
        Route::get('/relatorios', ReportController::class)->name('reports.index');
        Route::middleware('services.module')->prefix('servicos')->name('services.')->group(function (): void {
            Route::get('/veiculos-equipamentos', [ServiceAssetController::class, 'index'])->name('assets.index');
            Route::get('/veiculos-equipamentos/{asset}', [ServiceAssetController::class, 'show'])->whereNumber('asset')->name('assets.show');
            Route::put('/clientes/{customer}/equipamentos/{asset}', [ServiceAssetController::class, 'update'])->whereNumber('customer')->whereNumber('asset')->name('assets.update');
            Route::post('/clientes/{customer}/equipamentos', [ServiceAssetController::class, 'store'])->whereNumber('customer')->name('assets.store');
            Route::patch('/clientes/{customer}/equipamentos/{asset}/situacao', [ServiceAssetController::class, 'status'])->whereNumber('customer')->whereNumber('asset')->name('assets.status');
            Route::get('/catalogo', [ServiceCatalogController::class, 'index'])->name('index');
            Route::post('/catalogo', [ServiceCatalogController::class, 'store'])->name('store');
            Route::put('/catalogo/{item}', [ServiceCatalogController::class, 'update'])->whereNumber('item')->name('update');
            Route::patch('/catalogo/{item}/status', [ServiceCatalogController::class, 'toggle'])->whereNumber('item')->name('toggle');
            Route::prefix('agenda')->name('appointments.')->group(function (): void {
                Route::get('/', [ServiceAppointmentController::class, 'index'])->name('index');
                Route::get('/novo', [ServiceAppointmentController::class, 'create'])->name('create');
                Route::post('/', [ServiceAppointmentController::class, 'store'])->middleware('throttle:20,1')->name('store');
                Route::put('/{appointment}', [ServiceAppointmentController::class, 'update'])->whereNumber('appointment')->name('update');
                Route::patch('/{appointment}/situacao', [ServiceAppointmentController::class, 'status'])->whereNumber('appointment')->name('status');
            });
            Route::prefix('orcamentos')->name('quotes.')->group(function (): void {
                Route::get('/', [ServiceQuoteController::class, 'index'])->name('index');
                Route::get('/novo', [ServiceQuoteController::class, 'create'])->name('create');
                Route::post('/', [ServiceQuoteController::class, 'store'])->middleware('throttle:15,1')->name('store');
                Route::get('/{quote}/editar', [ServiceQuoteController::class, 'edit'])->whereNumber('quote')->name('edit');
                Route::put('/{quote}', [ServiceQuoteController::class, 'update'])->whereNumber('quote')->name('update');
                Route::get('/{quote}', [ServiceQuoteController::class, 'show'])->whereNumber('quote')->name('show');
                Route::post('/{quote}/enviar', [ServiceQuoteController::class, 'markSent'])->whereNumber('quote')->name('sent');
                Route::post('/{quote}/decisao', [ServiceQuoteController::class, 'decide'])->whereNumber('quote')->name('decide');
            });
            Route::prefix('ordens-de-servico')->name('work-orders.')->group(function (): void {
                Route::get('/', [ServiceWorkOrderController::class, 'index'])->name('index');
                Route::get('/{workOrder}', [ServiceWorkOrderController::class, 'show'])->whereNumber('workOrder')->name('show');
                Route::post('/a-partir-do-orcamento/{quote}', [ServiceWorkOrderController::class, 'createFromQuote'])->whereNumber('quote')->middleware('throttle:10,1')->name('from-quote');
                Route::post('/{workOrder}/devolucoes', [ServiceWorkOrderController::class, 'refund'])->whereNumber('workOrder')->middleware('throttle:10,1')->name('refund');
                Route::patch('/{workOrder}/situacao', [ServiceWorkOrderController::class, 'updateStatus'])->whereNumber('workOrder')->middleware('throttle:20,1')->name('status');
            });
        });
    });
});
