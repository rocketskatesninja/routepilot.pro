<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Api\FieldController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\PlatformAiController;
use App\Http\Controllers\PlatformBillingController;
use App\Http\Controllers\PoolController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PublicChatController;
use App\Http\Controllers\PublicPayController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\VisitController;
use App\Http\Middleware\EnsureBillingActive;
use App\Models\Customer;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public root: a custom-domain host renders that tenant's landing; the bare
// platform host falls back to RoutePilot's own marketing (inside the controller).
Route::get('/', [PublicSiteController::class, 'show'])->name('home');

// Tenant public sites on the platform host are PATH-based (not subdomains):
// routepilot.pro/t/{slug}. Custom domains serve the landing at their own root.
Route::get('t/{tenant:slug}', [PublicSiteController::class, 'showBySlug'])->name('public.site');

// Public legal pages (no auth, no tenant).
Route::get('privacy', fn () => Inertia::render('Privacy'))->name('privacy');
Route::get('terms', fn () => Inertia::render('Terms'))->name('terms');

// Public one-click unsubscribe (signed) — sets the marketing suppression flag.
Route::get('unsubscribe/{customer}', function (Customer $customer) {
    $customer->forceFill(['email_opt_out' => true])->save();

    return response('You have been unsubscribed from marketing emails.');
})->name('unsubscribe')->middleware('signed');

// Public lead capture from a tenant's landing site (by slug), rate-limited.
Route::post('public/{tenant:slug}/leads', [LeadController::class, 'store'])->middleware('throttle:10,1')->name('leads.capture');

// Public lead-capture chatbot (anonymous, per tenant) — metered + IP rate-limited.
Route::post('public/{tenant:slug}/chat', [PublicChatController::class, 'send'])->middleware('throttle:20,60')->name('public.chat');

// Stripe webhook — fails closed (signature-verified), idempotent. CSRF-exempt.
Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');

// Public, signed "pay your bill" link emailed to customers (no login).
Route::get('pay/thanks', fn () => view('pay.thanks'))->name('pay.thanks');
Route::get('pay/{customer}', [PublicPayController::class, 'pay'])->whereNumber('customer')->middleware('signed')->name('pay.link');

// Role-adaptive dashboard + back-office (staff). Tenant is resolved from the
// session user by ResolveTenant.
Route::middleware(['auth', 'verified', EnsureBillingActive::class])->group(function () {
    // ---- All authenticated roles (role-adaptive / self-scoped) ----
    // Shown when a tenant's billing is soft-locked (trial lapsed, no subscription).
    Route::get('paused', fn () => Inertia::render('Paused'))->name('account.paused');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('dashboard/layout', [DashboardController::class, 'saveLayout'])->name('dashboard.layout');
    // Invoice PDF: the owning customer OR staff (ownership enforced in the controller).
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'download'])->name('invoices.pdf');
    // In-app notifications + AI assistant.
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readall');
    Route::get('assistant', [ChatController::class, 'index'])->name('assistant.index');
    Route::post('assistant/send', [ChatController::class, 'send'])->name('assistant.send');
    // Reachable while impersonating (guarded by the session impersonator flag).
    Route::post('impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');

    // ---- Staff (agent + tenant_admin): reads + field/visit work + agent-capable actions ----
    Route::middleware('role:agent,tenant_admin,super_admin')->group(function () {
        Route::get('schedule', [ScheduleController::class, 'index'])->name('schedule.index');
        Route::post('stops/{stop}/skip', [ScheduleController::class, 'skipStop'])->name('stops.skip');
        // optimize/arrange: admin OR the Agent+ who owns the route (enforced in the controller).
        Route::post('routes/{route}/optimize', [ScheduleController::class, 'optimize'])->name('routes.optimize');
        Route::post('schedule/arrange', [ScheduleController::class, 'arrange'])->name('schedule.arrange');

        // Offline field app (the agent PWA surface) + its JSON API (session-auth,
        // same-origin; `/api/*` so the service worker leaves it alone).
        Route::get('field', fn () => Inertia::render('field/Index'))->name('field.index');
        Route::get('api/field/today', [FieldController::class, 'today'])->name('field.today');
        Route::post('api/field/visits/{stop}/complete', [FieldController::class, 'complete'])->name('field.complete');
        Route::post('api/field/ping', [FieldController::class, 'ping'])->middleware('throttle:12,1')->name('field.ping');

        // Agent at-pool visit flow (assigned agent or admin — enforced in the controller).
        Route::get('visit/{stop}', [VisitController::class, 'show'])->name('visit.show');
        Route::post('visit/{stop}/analyze', [VisitController::class, 'analyze'])->name('visit.analyze');
        Route::post('visit/{stop}/complete', [VisitController::class, 'complete'])->name('visit.complete');

        Route::get('pools', [PoolController::class, 'index'])->name('pools.index');
        Route::get('services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::patch('reports/{visit}', [ReportController::class, 'update'])->name('reports.update'); // admin or own agent
        Route::get('balances', [BalanceController::class, 'index'])->name('balances.index');
        Route::get('people', [PeopleController::class, 'index'])->name('people.index'); // super → platform view
    });

    // ---- Back-office writes + admin screens (tenant_admin) ----
    Route::middleware('role:tenant_admin')->group(function () {
        Route::post('schedule/materialize', [ScheduleController::class, 'materialize'])->name('schedule.materialize');
        Route::post('photos/{photo}/showcase', [VisitController::class, 'toggleShowcase'])->name('photos.showcase');

        Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::patch('subscriptions/{subscription}', [SubscriptionController::class, 'update'])->name('subscriptions.update');
        Route::delete('subscriptions/{subscription}', [SubscriptionController::class, 'destroy'])->name('subscriptions.destroy');
        Route::post('pools', [PoolController::class, 'store'])->name('pools.store');
        Route::patch('pools/{pool}', [PoolController::class, 'update'])->name('pools.update');
        Route::delete('pools/{pool}', [PoolController::class, 'destroy'])->name('pools.destroy');
        Route::post('pools/{pool}/targets', [PoolController::class, 'updateTargets'])->name('pools.targets');
        Route::post('equipment', [EquipmentController::class, 'store'])->name('equipment.store');
        Route::patch('equipment/{equipment}', [EquipmentController::class, 'update'])->name('equipment.update');
        Route::delete('equipment/{equipment}', [EquipmentController::class, 'destroy'])->name('equipment.destroy');
        Route::post('equipment/{equipment}/service', [EquipmentController::class, 'logService'])->name('equipment.service');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::patch('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        Route::post('customers/{customer}/portal', [CustomerController::class, 'grantPortal'])->name('customers.portal');
        Route::get('customers/{customer}/export', [CustomerController::class, 'export'])->name('customers.export');
        Route::post('agents', [AgentController::class, 'store'])->name('agents.store');
        Route::patch('agents/{agent}', [AgentController::class, 'update'])->name('agents.update');
        Route::patch('agents/{agent}/color', [AgentController::class, 'updateColor'])->name('agents.color');
        Route::delete('agents/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy');
        Route::post('services', [ServiceController::class, 'store'])->name('services.store');
        Route::patch('services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
        Route::post('inventory', [InventoryController::class, 'store'])->name('inventory.store');
        Route::patch('inventory/{chemical}', [InventoryController::class, 'update'])->name('inventory.update');
        Route::post('inventory/{chemical}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
        Route::delete('inventory/{chemical}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
        Route::get('insights', [AnalyticsController::class, 'index'])->name('insights.index');
        Route::patch('leads/{lead}', [LeadController::class, 'updateStatus'])->name('leads.status');
        Route::post('balances/charges', [BalanceController::class, 'addCharge'])->name('balances.charge');
        Route::post('balances/{customer}/pay', [BalanceController::class, 'recordPayment'])->name('balances.pay');
        Route::post('balances/{customer}/invoice', [BalanceController::class, 'generateInvoice'])->name('balances.invoice');
        Route::get('balances/export', [BalanceController::class, 'exportCsv'])->name('balances.export');
        Route::post('invoices/{invoice}/email', [InvoiceController::class, 'email'])->name('invoices.email');
        Route::post('invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->name('invoices.markpaid');
        Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
        Route::post('requests/{serviceRequest}/resolve', [RequestController::class, 'resolve'])->name('requests.resolve');

        // Platform billing for the tenant (trial/subscription + Stripe Checkout/portal).
        Route::get('billing', [BillingController::class, 'show'])->name('billing.show');
        Route::post('billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
        Route::get('billing/portal', [BillingController::class, 'portal'])->name('billing.portal');

        // Company settings.
        Route::get('company', [CompanySettingsController::class, 'edit'])->name('company.edit');
        Route::patch('company', [CompanySettingsController::class, 'update'])->name('company.update');
        Route::patch('company/mail', [CompanySettingsController::class, 'updateMail'])->name('company.mail');
        Route::post('company/connect', [CompanySettingsController::class, 'connect'])->name('company.connect');
        Route::get('company/connect/return', [CompanySettingsController::class, 'connectReturn'])->name('company.connect.return');

        // Landing-page builder. Config save is pure JSON; images via a dedicated endpoint.
        Route::get('company/landing', [LandingController::class, 'edit'])->name('company.landing.edit');
        Route::post('company/landing', [LandingController::class, 'update'])->name('company.landing.update');
        Route::post('company/landing/image', [LandingController::class, 'uploadImage'])->name('company.landing.image');
    });

    // Broadcast email — tenant_admin (own tenant) or super_admin (platform-wide).
    Route::post('people/email', [MailController::class, 'send'])->middleware('role:tenant_admin,super_admin')->name('people.email');

    // ---- Customer portal (customer) ----
    Route::middleware('role:customer')->group(function () {
        Route::get('history', [PortalController::class, 'history'])->name('portal.history');
        Route::get('requests', [PortalController::class, 'requests'])->name('portal.requests');
        Route::post('requests', [RequestController::class, 'store'])->name('requests.store');
        Route::get('balance', [PortalController::class, 'balance'])->name('portal.balance');
        Route::post('balance/pay', [PortalController::class, 'pay'])->name('portal.pay');
        Route::post('autopay/setup', [PortalController::class, 'setupAutopay'])->name('portal.autopay.setup');
        Route::get('autopay/complete', [PortalController::class, 'autopayComplete'])->name('portal.autopay.complete');
        Route::post('autopay/disable', [PortalController::class, 'disableAutopay'])->name('portal.autopay.disable');
    });

    // ---- Super-admin platform console ----
    Route::middleware('role:super_admin')->group(function () {
        Route::get('platform/billing', [PlatformBillingController::class, 'index'])->name('platform.billing');
        Route::patch('platform/billing/tenants/{tenant}', [PlatformBillingController::class, 'update'])->name('platform.billing.update');
        Route::get('platform/ai', [PlatformAiController::class, 'edit'])->name('platform.ai.edit');
        Route::patch('platform/ai', [PlatformAiController::class, 'update'])->name('platform.ai.update');
        Route::patch('platform/ai/tenants/{tenant}', [PlatformAiController::class, 'updateTenant'])->name('platform.ai.tenant');
        Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::patch('tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
        Route::post('tenants/{tenant}/impersonate', [ImpersonationController::class, 'start'])->name('tenants.impersonate');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
