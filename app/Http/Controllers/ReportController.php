<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Client;
use App\Models\Location;
use App\Http\Requests\ReportRequest;
use App\Services\PhotoUploadService;
use App\Traits\HasSearchable;
use App\Traits\HasSortable;
use App\Traits\HasExportable;
use App\Constants\AppConstants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ReportController extends Controller
{
    use HasSearchable, HasSortable, HasExportable;

    protected $photoUploadService;

    public function __construct(PhotoUploadService $photoUploadService)
    {
        $this->photoUploadService = $photoUploadService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        if ($user->role === AppConstants::ROLE_ADMIN || $user->role === AppConstants::ROLE_TECHNICIAN) {
            $query = Report::with(['client', 'location', 'technician']);

            // Apply search
            $searchTerm = $this->getSearchTerm($request);
            $this->applySearch($query, ['client.first_name', 'client.last_name', 'location.nickname', 'technician.first_name', 'technician.last_name'], $searchTerm);

            // Apply filters
            $this->applyFilters($query, $request, [
                'date_from' => ['column' => 'service_date', 'operator' => '>='],
                'date_to' => ['column' => 'service_date', 'operator' => '<='],
            ]);

            // Apply sorting
            $sortOptions = [
                'date_desc' => ['column' => 'service_date', 'direction' => 'desc'],
                'date_asc' => ['column' => 'service_date', 'direction' => 'asc'],
                'status' => ['column' => 'created_at', 'direction' => 'desc'],
            ];
            $this->applySorting($query, $sortOptions, 'service_date');

            $reports = $query->paginate(AppConstants::DEFAULT_PAGINATION);
            $clients = \App\Models\Client::orderBy('last_name')->get();
            $locations = \App\Models\Location::orderBy('nickname')->get();
            $technicians = \App\Models\User::where('role', 'technician')->orderBy('last_name')->get();
            
            // Calculate stats
            $stats = [
                'total' => Report::count(),
                'this_month' => Report::where('service_date', '>=', Carbon::now()->startOfMonth())->count(),
                'this_week' => Report::where('service_date', '>=', Carbon::now()->startOfWeek())->count(),
            ];
        } elseif ($user->role === AppConstants::ROLE_CLIENT) {
            // For customers, get reports through their client record
            $client = Client::where('email', $user->email)->first();
            if ($client) {
                $query = Report::where('client_id', $client->id)->with(['client', 'location', 'technician']);
                
                // Apply search
                $searchTerm = $this->getSearchTerm($request);
                $this->applySearch($query, ['client.first_name', 'client.last_name', 'location.nickname', 'technician.first_name', 'technician.last_name'], $searchTerm);

                // Apply filters
                $this->applyFilters($query, $request, [
                    'date_from' => ['column' => 'service_date', 'operator' => '>='],
                    'date_to' => ['column' => 'service_date', 'operator' => '<='],
                ]);

                // Apply sorting
                $sortOptions = [
                    'date_desc' => ['column' => 'service_date', 'direction' => 'desc'],
                    'date_asc' => ['column' => 'service_date', 'direction' => 'asc'],
                    'status' => ['column' => 'created_at', 'direction' => 'desc'],
                ];
                $this->applySorting($query, $sortOptions, 'service_date');

                $reports = $query->paginate(AppConstants::DEFAULT_PAGINATION);
                
                // Calculate stats for customer's reports only
                $stats = [
                    'total' => $client->reports()->count(),
                    'this_month' => $client->reports()->where('service_date', '>=', Carbon::now()->startOfMonth())->count(),
                    'this_week' => $client->reports()->where('service_date', '>=', Carbon::now()->startOfWeek())->count(),
                ];
                
                $clients = collect([$client]); // Only show their own client record
                $locations = $client->locations()->orderBy('nickname')->get();
                $technicians = collect(); // Empty collection for customers
            } else {
                $query = Report::where('id', 0); // Empty query if no client found
                $reports = $query->paginate(AppConstants::DEFAULT_PAGINATION);
                $stats = ['total' => 0, 'this_month' => 0, 'this_week' => 0];
                $clients = collect();
                $locations = collect();
                $technicians = collect();
            }
        } else {
            abort(403);
        }
        
        return view('reports.index', compact('reports', 'clients', 'locations', 'technicians', 'stats'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $user = Auth::user();
        if ($user->role === 'admin' || $user->role === 'technician') {
            $technicians = \App\Models\User::where('role', 'technician')->orderBy('last_name')->get();
            $locationId = $request->get('location_id');
            
            // If location_id is provided, get the location and client data for auto-population
            $location = null;
            $client = null;
            if ($locationId) {
                $location = \App\Models\Location::with('client')->find($locationId);
                if ($location) {
                    $client = $location->client;
                }
            }
            
            return view('reports.create', compact('technicians', 'locationId', 'location', 'client'));
        }
        abort(403);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ReportRequest $request)
    {
        $user = Auth::user();
        if (!($user->role === AppConstants::ROLE_ADMIN || $user->role === AppConstants::ROLE_TECHNICIAN)) {
            abort(403);
        }
        
        $validated = $request->validated();
        if (empty($validated['technician_id'])) {
            $validated['technician_id'] = $user->id;
        }
        
        // Handle arrays
        $validated['chemicals_used'] = $request->input('chemicals_used', []);
        $validated['other_services'] = $request->input('other_services', []);
        
        // Convert single string values to arrays
        if (!empty($validated['chemicals_used']) && is_string($validated['chemicals_used'])) {
            $validated['chemicals_used'] = [$validated['chemicals_used']];
        }
        if (!empty($validated['other_services']) && is_string($validated['other_services'])) {
            $validated['other_services'] = [$validated['other_services']];
        }
        
        // Handle NULL values for cost fields
        $validated['chemicals_cost'] = $validated['chemicals_cost'] ?? 0.00;
        $validated['other_services_cost'] = $validated['other_services_cost'] ?? 0.00;
        $validated['total_cost'] = $validated['total_cost'] ?? 0.00;
        
        // Handle photo uploads
        $validated['photos'] = $this->photoUploadService->handlePhotoUploads(
            $request, 
            'reports/photos'
        );
        
        // Checkbox booleans
        foreach ([
            'vacuumed','brushed','skimmed','cleaned_skimmer_basket','cleaned_pump_basket','cleaned_pool_deck',
            'cleaned_filter_cartridge','backwashed_sand_filter','adjusted_water_level','adjusted_auto_fill','adjusted_pump_timer','adjusted_heater','checked_cover','checked_lights','checked_fountain','checked_heater'
        ] as $field) {
            $validated[$field] = $request->has($field);
        }

        // Get rate per visit from location or use 0
        $ratePerVisit = \App\Models\Location::find($validated['location_id'])->rate_per_visit ?? 0.00;
        $validated['total_cost'] = ($ratePerVisit ?? 0) + ($validated['chemicals_cost'] ?? 0) + ($validated['other_services_cost'] ?? 0);

        $report = Report::create($validated);
        
        // Generate invoice if requested
        if ($request->has('generate_invoice')) {
            $invoice = $this->generateInvoiceFromReport($report);
            $report->update(['invoice_id' => $invoice->id]);
        }
        
        // Redirect to chemical calculator if requested
        if ($request->has('send_to_calculator')) {
            $location = \App\Models\Location::find($validated['location_id']);
            $poolVolume = $location->pool_volume ?? 15000; // Default to 15,000 gallons if not set
            
            $calculatorData = [
                'pool_volume' => $poolVolume,
                'current_ph' => $validated['ph'] ?? 7.4,
                'target_ph' => 7.4,
                'current_chlorine' => $validated['fac'] ?? 2.0,
                'target_chlorine' => 2.0,
                'current_alkalinity' => $validated['alkalinity'] ?? 100,
                'target_alkalinity' => 100,
                'current_calcium' => $validated['calcium'] ?? 250,
                'target_calcium' => 250,
                'current_cyanuric_acid' => $validated['cya'] ?? 50,
                'target_cyanuric_acid' => 50,
            ];
            
            return redirect()->route('chem-calc')->with('calculator_data', $calculatorData);
        }
        
        return redirect()->route('reports.show', $report)->with('success', 'Report submitted.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $user = Auth::user();
        $report = Report::with(['client', 'location', 'technician', 'invoice'])->findOrFail($id);
        
        if ($user->role === 'admin' || $user->role === 'technician') {
            return view('reports.show', compact('report'));
        } elseif ($user->role === AppConstants::ROLE_CLIENT) {
            // Check if this report belongs to the customer
            $client = Client::where('email', $user->email)->first();
            if ($client && $report->client_id === $client->id) {
                return view('reports.show', compact('report'));
            } else {
                abort(403, 'You can only view your own reports.');
            }
        } else {
            abort(403);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $user = Auth::user();
        if ($user->role === 'admin' || $user->role === 'technician') {
            $report = Report::with(['client', 'location', 'technician'])->findOrFail($id);
            $technicians = \App\Models\User::where('role', 'technician')->orderBy('last_name')->get();
            return view('reports.edit', compact('report', 'technicians'));
        }
        abort(403);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ReportRequest $request, $id)
    {
        $user = Auth::user();
        if (!($user->role === AppConstants::ROLE_ADMIN || $user->role === AppConstants::ROLE_TECHNICIAN)) {
            abort(403);
        }

        $report = Report::findOrFail($id);
        
        $validated = $request->validated();

        // Handle arrays
        $validated['chemicals_used'] = $request->input('chemicals_used', []);
        $validated['other_services'] = $request->input('other_services', []);
        
        // Convert single string values to arrays
        if (!empty($validated['chemicals_used']) && is_string($validated['chemicals_used'])) {
            $validated['chemicals_used'] = [$validated['chemicals_used']];
        }
        if (!empty($validated['other_services']) && is_string($validated['other_services'])) {
            $validated['other_services'] = [$validated['other_services']];
        }
        
        // Handle NULL values for cost fields
        $validated['chemicals_cost'] = $validated['chemicals_cost'] ?? 0.00;
        $validated['other_services_cost'] = $validated['other_services_cost'] ?? 0.00;
        $validated['total_cost'] = $validated['total_cost'] ?? 0.00;
        
        // Handle photo uploads
        $validated['photos'] = $this->photoUploadService->handlePhotoUploads(
            $request, 
            'reports/photos',
            $report->photos ?? []
        );
        
        // Checkbox booleans
        foreach ([
            'vacuumed','brushed','skimmed','cleaned_skimmer_basket','cleaned_pump_basket','cleaned_pool_deck',
            'cleaned_filter_cartridge','backwashed_sand_filter','adjusted_water_level','adjusted_auto_fill','adjusted_pump_timer','adjusted_heater','checked_cover','checked_lights','checked_fountain','checked_heater'
        ] as $field) {
            $validated[$field] = $request->has($field);
        }

        // Get rate per visit from location or use 0
        $ratePerVisit = \App\Models\Location::find($validated['location_id'])->rate_per_visit ?? 0.00;
        $validated['total_cost'] = ($ratePerVisit ?? 0) + ($validated['chemicals_cost'] ?? 0) + ($validated['other_services_cost'] ?? 0);

        $report->update($validated);
        
        // Generate invoice if requested and no invoice exists yet
        if ($request->has('generate_invoice') && !$report->invoice_id) {
            $invoice = $this->generateInvoiceFromReport($report);
            $report->update(['invoice_id' => $invoice->id]);
        }
        
        // Redirect to chemical calculator if requested
        if ($request->has('send_to_calculator')) {
            $location = \App\Models\Location::find($validated['location_id']);
            $poolVolume = $location->pool_volume ?? 15000; // Default to 15,000 gallons if not set
            
            $calculatorData = [
                'pool_volume' => $poolVolume,
                'current_ph' => $validated['ph'] ?? 7.4,
                'target_ph' => 7.4,
                'current_chlorine' => $validated['fac'] ?? 2.0,
                'target_chlorine' => 2.0,
                'current_alkalinity' => $validated['alkalinity'] ?? 100,
                'target_alkalinity' => 100,
                'current_calcium' => $validated['calcium'] ?? 250,
                'target_calcium' => 250,
                'current_cyanuric_acid' => $validated['cya'] ?? 50,
                'target_cyanuric_acid' => 50,
            ];
            
            return redirect()->route('chem-calc')->with('calculator_data', $calculatorData);
        }
        
        return redirect()->route('reports.show', $report)->with('success', 'Report updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $user = Auth::user();
        if (!($user->role === 'admin' || $user->role === 'technician')) {
            abort(403);
        }

        $report = Report::findOrFail($id);
        
        // Delete photos from storage
        if ($report->photos) {
            foreach ($report->photos as $photo) {
                \Storage::disk('public')->delete($photo);
            }
        }
        
        $report->delete();
        return redirect()->route('reports.index')->with('success', 'Report deleted successfully.');
    }

    /**
     * Generate an invoice from a report.
     */
    private function generateInvoiceFromReport(Report $report)
    {
        // Generate invoice number
        $lastInvoice = \App\Models\Invoice::orderBy('id', 'desc')->first();
        $invoiceNumber = $lastInvoice ? $lastInvoice->id + 1 : 1;
        $formattedInvoiceNumber = 'INV-' . str_pad($invoiceNumber, 6, '0', STR_PAD_LEFT);

        // Get rate per visit from location or use 0
        $ratePerVisit = $report->location->rate_per_visit ?? 0.00;

        // Calculate total amount correctly
        $totalAmount = ($ratePerVisit ?? 0) + ($report->chemicals_cost ?? 0) + ($report->other_services_cost ?? 0);

        // Create the invoice
        $invoice = \App\Models\Invoice::create([
            'invoice_number' => $formattedInvoiceNumber,
            'client_id' => $report->client_id,
            'location_id' => $report->location_id,
            'technician_id' => $report->technician_id,
            'service_date' => $report->service_date,
            'due_date' => now()->addDays(30), // 30 days from now
            'rate_per_visit' => $ratePerVisit,
            'chemicals_cost' => $report->chemicals_cost ?? 0.00,
            'chemicals_included' => false,
            'extras_cost' => $report->other_services_cost ?? 0.00,
            'total_amount' => $totalAmount,
            'balance' => $totalAmount,
            'status' => 'draft',
            'notes' => "Invoice generated from service report #{$report->id}",
            'notification_sent' => false,
        ]);

        return $invoice;
    }
}
