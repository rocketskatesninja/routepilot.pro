<?php

use App\Http\Controllers\BalanceController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\PoolController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Role-adaptive dashboard + back-office (staff). Tenant is resolved from the
// session user by ResolveTenant.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::get('pools', [PoolController::class, 'index'])->name('pools.index');
    Route::post('pools', [PoolController::class, 'store'])->name('pools.store');
    Route::patch('pools/{pool}', [PoolController::class, 'update'])->name('pools.update');
    Route::delete('pools/{pool}', [PoolController::class, 'destroy'])->name('pools.destroy');
    Route::get('people', [PeopleController::class, 'index'])->name('people.index');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::patch('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    Route::get('services', [ServiceController::class, 'index'])->name('services.index');
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('balances', [BalanceController::class, 'index'])->name('balances.index');

    // AI assistant (all roles).
    Route::get('assistant', [ChatController::class, 'index'])->name('assistant.index');
    Route::post('assistant/send', [ChatController::class, 'send'])->name('assistant.send');

    // Company settings (tenant_admin).
    Route::get('company', [CompanySettingsController::class, 'edit'])->name('company.edit');
    Route::patch('company', [CompanySettingsController::class, 'update'])->name('company.update');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
