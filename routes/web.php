<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\TechnicianController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Client Management Routes
    Route::resource('clients', ClientController::class);
    Route::get('/clients/{client}/toggle-status', [ClientController::class, 'toggleStatus'])->name('clients.toggle-status');
    Route::get('/clients/export/csv', [ClientController::class, 'export'])->name('clients.export');
    Route::get('/api/clients/search', [ClientController::class, 'search'])->name('api.clients.search');
    Route::get('/api/clients/{client}/locations', [ClientController::class, 'getLocations'])->name('api.clients.locations');
    
    // Location Management Routes
    Route::resource('locations', LocationController::class);
    Route::get('/locations/{location}/toggle-favorite', [LocationController::class, 'toggleFavorite'])->name('locations.toggle-favorite');
    Route::get('/locations/{location}/toggle-status', [LocationController::class, 'toggleStatus'])->name('locations.toggle-status');
    Route::get('/locations/export/csv', [LocationController::class, 'export'])->name('locations.export');
    Route::get('/api/locations/{location}', [LocationController::class, 'showApi'])->name('api.locations.show');
    
    // Technician Management Routes
    Route::resource('technicians', TechnicianController::class);
    Route::get('/technicians/{technician}/toggle-status', [TechnicianController::class, 'toggleStatus'])->name('technicians.toggle-status');
    Route::get('/technicians/export/csv', [TechnicianController::class, 'export'])->name('technicians.export');
    
    // Invoice Management Routes
    Route::resource('invoices', InvoiceController::class);
    Route::get('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markAsPaid'])->name('invoices.mark-paid');
    Route::post('/invoices/{invoice}/record-payment', [InvoiceController::class, 'recordPayment'])->name('invoices.record-payment');
    Route::get('/invoices/export/csv', [InvoiceController::class, 'export'])->name('invoices.export');
    Route::get('/invoices/statistics', [InvoiceController::class, 'statistics'])->name('invoices.statistics');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'generatePdf'])->name('invoices.pdf');
    Route::get('/invoices/{invoice}/pdf/view', [InvoiceController::class, 'viewPdf'])->name('invoices.pdf.view');

    // Recurring Billing Profiles Management
    // Route::resource('recurring-billing-profiles', RecurringBillingProfileController::class);

    // Reports Management
    Route::resource('reports', ReportController::class);

    // Chemical Calculator Placeholder
    Route::get('/chem-calc', function () {
        return view('chem-calc');
    })->name('chem-calc');
});

require __DIR__.'/auth.php';
