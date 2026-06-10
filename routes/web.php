<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\PoolController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SubscriptionController;
use App\Models\Customer;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Public one-click unsubscribe (signed) — sets the marketing suppression flag.
Route::get('unsubscribe/{customer}', function (Customer $customer) {
    $customer->forceFill(['email_opt_out' => true])->save();

    return response('You have been unsubscribed from marketing emails.');
})->name('unsubscribe')->middleware('signed');

// Role-adaptive dashboard + back-office (staff). Tenant is resolved from the
// session user by ResolveTenant.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::post('schedule/materialize', [ScheduleController::class, 'materialize'])->name('schedule.materialize');
    Route::post('routes/{route}/optimize', [ScheduleController::class, 'optimize'])->name('routes.optimize');
    Route::post('stops/{stop}/skip', [ScheduleController::class, 'skipStop'])->name('stops.skip');
    Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
    Route::patch('subscriptions/{subscription}', [SubscriptionController::class, 'update'])->name('subscriptions.update');
    Route::delete('subscriptions/{subscription}', [SubscriptionController::class, 'destroy'])->name('subscriptions.destroy');
    Route::get('pools', [PoolController::class, 'index'])->name('pools.index');
    Route::post('pools', [PoolController::class, 'store'])->name('pools.store');
    Route::patch('pools/{pool}', [PoolController::class, 'update'])->name('pools.update');
    Route::delete('pools/{pool}', [PoolController::class, 'destroy'])->name('pools.destroy');
    Route::get('people', [PeopleController::class, 'index'])->name('people.index');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::patch('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    Route::post('customers/{customer}/portal', [CustomerController::class, 'grantPortal'])->name('customers.portal');
    Route::get('mail', [MailController::class, 'index'])->name('mail.index');
    Route::post('mail', [MailController::class, 'send'])->name('mail.send');
    Route::post('agents', [AgentController::class, 'store'])->name('agents.store');
    Route::patch('agents/{agent}', [AgentController::class, 'update'])->name('agents.update');
    Route::delete('agents/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy');
    Route::get('services', [ServiceController::class, 'index'])->name('services.index');
    Route::post('services', [ServiceController::class, 'store'])->name('services.store');
    Route::patch('services/{service}', [ServiceController::class, 'update'])->name('services.update');
    Route::delete('services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::patch('inventory/{chemical}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::post('inventory/{chemical}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
    Route::delete('inventory/{chemical}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('balances', [BalanceController::class, 'index'])->name('balances.index');
    Route::post('balances/charges', [BalanceController::class, 'addCharge'])->name('balances.charge');
    Route::post('balances/{customer}/pay', [BalanceController::class, 'recordPayment'])->name('balances.pay');

    // AI assistant (all roles).
    Route::get('assistant', [ChatController::class, 'index'])->name('assistant.index');
    Route::post('assistant/send', [ChatController::class, 'send'])->name('assistant.send');

    // Company settings (tenant_admin).
    Route::get('company', [CompanySettingsController::class, 'edit'])->name('company.edit');
    Route::patch('company', [CompanySettingsController::class, 'update'])->name('company.update');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
