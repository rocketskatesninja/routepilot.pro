<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\TechnicianController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ChemicalCalculatorController;
use App\Http\Controllers\Admin\SettingsController;
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
    Route::post('/profile/delete-photo', [ProfileController::class, 'deletePhoto'])->name('profile.delete-photo');
    
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
    Route::post('/locations/{location}/delete-photo', [LocationController::class, 'deletePhoto'])->name('locations.delete-photo');
    
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
    Route::post('/reports/{report}/delete-photo', [ReportController::class, 'deletePhoto'])->name('reports.delete-photo');

    // Chemical Calculator
    Route::get('/chem-calc', [ChemicalCalculatorController::class, 'index'])->name('chem-calc');
    Route::post('/chem-calc', [ChemicalCalculatorController::class, 'calculate'])->name('chem-calc.calculate');
    
    // Map API
    Route::get('/map/geocode', function (Request $request) {
        $address = $request->get('address');
        if (!$address) {
            return response()->json(['success' => false, 'error' => 'Address parameter is required']);
        }
        
        $coordinates = \App\Services\MapService::geocodeAddress($address);
        if ($coordinates) {
            return response()->json(['success' => true, 'coordinates' => $coordinates]);
        }
        
        return response()->json(['success' => false, 'error' => 'Address not found']);
    })->name('map.geocode');
    
    // Admin Settings Routes
    Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general');
        Route::post('/settings/database', [SettingsController::class, 'updateDatabase'])->name('settings.database');
        Route::post('/settings/mail', [SettingsController::class, 'updateMail'])->name('settings.mail');
        Route::post('/settings/security', [SettingsController::class, 'updateSecurity'])->name('settings.security');
        Route::post('/settings/backup', [SettingsController::class, 'createBackup'])->name('settings.backup');
        Route::get('/settings/backup/{filename}/download', [SettingsController::class, 'downloadBackup'])->name('settings.backup.download');
        Route::delete('/settings/backup/{filename}', [SettingsController::class, 'deleteBackup'])->name('settings.backup.delete');
        Route::post('/settings/test-mail', [SettingsController::class, 'testMail'])->name('settings.test-mail');
        Route::get('/settings/email-logs', [SettingsController::class, 'getEmailLogs'])->name('settings.email-logs');
        Route::get('/settings/uploads', [SettingsController::class, 'uploads'])->name('settings.uploads');
        Route::put('/settings/uploads', [SettingsController::class, 'updateUploads'])->name('settings.uploads');
    });
});

require __DIR__.'/auth.php';
