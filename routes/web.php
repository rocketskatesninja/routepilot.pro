<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\PoolController;
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
    Route::get('pools', [PoolController::class, 'index'])->name('pools.index');
    Route::get('people', [PeopleController::class, 'index'])->name('people.index');
    Route::get('services', [ServiceController::class, 'index'])->name('services.index');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
