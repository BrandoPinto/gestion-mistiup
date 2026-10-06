<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Billing\ChargeActionsController;
use App\Http\Controllers\Billing\ChargeController;
use App\Http\Controllers\Billing\PaymentController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\Catalog\ServiceController;
use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\Clients\ClientSearchController;
use App\Http\Controllers\Contracts\CancelContractController;
use App\Http\Controllers\Contracts\ContractController;
use App\Http\Controllers\Contracts\ContractScheduleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DueController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Quotes\PublicQuoteController;
use App\Http\Controllers\Quotes\QuoteController;
use App\Http\Controllers\Quotes\QuoteConversionController;
use App\Http\Controllers\Quotes\QuoteDocumentController;
use App\Http\Controllers\Quotes\QuoteStatusController;
use App\Http\Controllers\Settings\BillingSettingsController;
use App\Http\Controllers\Settings\CompanyProfileController;
use App\Http\Controllers\Settings\PaymentMethodController;
use App\Http\Controllers\Settings\ReminderRuleController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Users\AccountController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Público a propósito: lo usan el enlace público de cotizaciones y el PDF.
Route::get('/marca/logo', [BrandingController::class, 'logo'])->name('branding.logo');

// Enlace público de cotizaciones: sin sesión, solo lectura, con límite de peticiones por IP.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/q/{token}', [PublicQuoteController::class, 'show'])->name('quotes.public');
    Route::get('/q/{token}/pdf', [PublicQuoteController::class, 'pdf'])->name('quotes.public.pdf');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    // Antes del resource para que "buscar" no se interprete como un {client}.
    Route::get('/clientes/buscar', ClientSearchController::class)->name('clients.search');
    Route::post('/clientes/rapido', [ClientSearchController::class, 'store'])->name('clients.quick-store');
    Route::resource('clientes', ClientController::class)
        ->parameters(['clientes' => 'client'])
        ->names('clients');

    Route::resource('catalogo', ServiceController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['catalogo' => 'service'])
        ->names('services');
    Route::patch('/catalogo/{service}/estado', [ServiceController::class, 'updateStatus'])->name('services.status');

    Route::post('/contratos/calendario', ContractScheduleController::class)->name('contracts.schedule');
    Route::resource('contratos', ContractController::class)
        ->except(['destroy'])
        ->parameters(['contratos' => 'contract'])
        ->names('contracts');
    Route::post('/contratos/{contract}/cancelar', CancelContractController::class)->name('contracts.cancel');

    Route::get('/cobros', [ChargeController::class, 'index'])->name('charges.index');
    Route::post('/cobros', [ChargeController::class, 'store'])->name('charges.store');
    Route::get('/cobros/{charge}', [ChargeController::class, 'show'])->name('charges.show');
    Route::patch('/cobros/{charge}/importe', [ChargeActionsController::class, 'adjustAmount'])->name('charges.amount');
    Route::post('/cobros/{charge}/cancelar', [ChargeActionsController::class, 'cancel'])->name('charges.cancel');
    Route::post('/cobros/{charge}/pagos', [PaymentController::class, 'store'])->middleware('throttle:30,1')->name('payments.store');

    Route::get('/pagos', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/pagos/{payment}/anular', [PaymentController::class, 'void'])->name('payments.void');

    Route::get('/adjuntos/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');

    Route::get('/vencimientos', DueController::class)->name('due.index');

    Route::get('/notificaciones', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notificaciones/recientes', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::post('/notificaciones/leer-todas', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notificaciones/{notification}/abrir', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notificaciones/{notification}/leer', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('/configuracion', SettingsController::class)->name('settings.index');
    Route::get('/configuracion/recordatorios', [ReminderRuleController::class, 'index'])->name('settings.reminders');
    Route::post('/configuracion/recordatorios', [ReminderRuleController::class, 'store'])->name('settings.reminders.store');
    Route::patch('/configuracion/recordatorios/{rule}', [ReminderRuleController::class, 'update'])->name('settings.reminders.update');
    Route::delete('/configuracion/recordatorios/{rule}', [ReminderRuleController::class, 'destroy'])->name('settings.reminders.destroy');
    Route::get('/configuracion/empresa', [CompanyProfileController::class, 'edit'])->name('settings.company');
    Route::put('/configuracion/empresa', [CompanyProfileController::class, 'update'])->name('settings.company.update');
    Route::post('/configuracion/empresa/logo', [CompanyProfileController::class, 'uploadLogo'])->name('settings.company.logo');
    Route::delete('/configuracion/empresa/logo', [CompanyProfileController::class, 'deleteLogo'])->name('settings.company.logo.delete');
    Route::get('/configuracion/metodos-pago', [PaymentMethodController::class, 'index'])->name('settings.payment-methods');
    Route::post('/configuracion/metodos-pago', [PaymentMethodController::class, 'store'])->name('settings.payment-methods.store');
    Route::patch('/configuracion/metodos-pago/{method}', [PaymentMethodController::class, 'update'])->name('settings.payment-methods.update');
    Route::post('/configuracion/metodos-pago/{method}/mover', [PaymentMethodController::class, 'move'])->name('settings.payment-methods.move');
    Route::get('/configuracion/cobros', [BillingSettingsController::class, 'edit'])->name('settings.billing');
    Route::put('/configuracion/cobros', [BillingSettingsController::class, 'update'])->name('settings.billing.update');

    Route::resource('cotizaciones', QuoteController::class)
        ->except(['destroy'])
        ->parameters(['cotizaciones' => 'quote'])
        ->names('quotes');
    Route::post('/cotizaciones/{quote}/enviada', [QuoteStatusController::class, 'send'])->name('quotes.send');
    Route::post('/cotizaciones/{quote}/borrador', [QuoteStatusController::class, 'draft'])->name('quotes.draft');
    Route::post('/cotizaciones/{quote}/cancelar', [QuoteStatusController::class, 'cancel'])->name('quotes.cancel');
    Route::get('/cotizaciones/{quote}/pdf', [QuoteDocumentController::class, 'pdf'])->name('quotes.pdf');
    Route::patch('/cotizaciones/{quote}/enlace', [QuoteDocumentController::class, 'togglePublic'])->name('quotes.link.toggle');
    Route::post('/cotizaciones/{quote}/enlace/regenerar', [QuoteDocumentController::class, 'regenerateLink'])->name('quotes.link.regenerate');
    Route::get('/cotizaciones/{quote}/convertir', [QuoteConversionController::class, 'create'])->name('quotes.convert');
    Route::post('/cotizaciones/{quote}/convertir', [QuoteConversionController::class, 'store'])->name('quotes.convert.store');

    Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
    Route::post('/usuarios', [UserController::class, 'store'])->name('users.store');
    Route::put('/usuarios/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('/usuarios/{user}/contrasena', [UserController::class, 'resetPassword'])->name('users.password');
    Route::patch('/usuarios/{user}/estado', [UserController::class, 'setStatus'])->name('users.status');

    Route::get('/cuenta', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/cuenta', [AccountController::class, 'update'])->name('account.update');
    Route::put('/cuenta/contrasena', [AccountController::class, 'updatePassword'])->middleware('throttle:10,1')->name('account.password');

    if (app()->isLocal()) {
        Route::get('/sistema/ui', fn () => Inertia::render('dev/UiKit'))->name('dev.ui');
    }
});
