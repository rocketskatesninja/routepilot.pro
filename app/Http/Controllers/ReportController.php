<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Client;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if ($user->role === 'admin' || $user->role === 'technician') {
            $query = Report::with(['client', 'location', 'technician']);

            // Search functionality
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('first_name', 'like', "%{$search}%")
                                   ->orWhere('last_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('location', function ($locationQuery) use ($search) {
                        $locationQuery->where('nickname', 'like', "%{$search}%");
                    })
                    ->orWhereHas('technician', function ($technicianQuery) use ($search) {
                        $technicianQuery->where('first_name', 'like', "%{$search}%")
                                       ->orWhere('last_name', 'like', "%{$search}%");
                    });
                });
            }

            // Filter by date range
            if ($request->filled('date_from')) {
                $query->where('service_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->where('service_date', '<=', $request->date_to);
            }

            // Sort functionality
            $sortBy = $request->get('sort_by', 'date_desc');
            
            switch ($sortBy) {
                case 'date_desc':
                    $query->orderBy('service_date', 'desc');
                    break;
                case 'date_asc':
                    $query->orderBy('service_date', 'asc');
                    break;
                case 'status':
                    $query->orderBy('created_at', 'desc');
                    break;
                default:
                    $query->orderBy('service_date', 'desc');
                    break;
            }

            $reports = $query->paginate(15);
            $clients = \App\Models\Client::orderBy('last_name')->get();
            $locations = \App\Models\Location::orderBy('nickname')->get();
            $technicians = \App\Models\User::where('role', 'technician')->orderBy('last_name')->get();
            
            // Calculate stats
            $stats = [
                'total' => Report::count(),
                'this_month' => Report::where('service_date', '>=', Carbon::now()->startOfMonth())->count(),
                'this_week' => Report::where('service_date', '>=', Carbon::now()->startOfWeek())->count(),
            ];
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
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!($user->role === 'admin' || $user->role === 'technician')) {
            abort(403);
        }
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'location_id' => 'required|exists:locations,id',
            'service_date' => 'required|date',
            'service_time' => 'required',
            'pool_gallons' => 'nullable|integer',
            // Chemistry
            'fac' => 'nullable|numeric',
            'cc' => 'nullable|numeric',
            'ph' => 'nullable|numeric',
            'alkalinity' => 'nullable|integer',
            'calcium' => 'nullable|integer',
            'salt' => 'nullable|integer',
            'cya' => 'nullable|integer',
            'tds' => 'nullable|integer',
            // Cleaning
            'vacuumed' => 'nullable|boolean',
            'brushed' => 'nullable|boolean',
            'skimmed' => 'nullable|boolean',
            'cleaned_skimmer_basket' => 'nullable|boolean',
            'cleaned_pump_basket' => 'nullable|boolean',
            'cleaned_pool_deck' => 'nullable|boolean',
            // Maintenance
            'cleaned_filter_cartridge' => 'nullable|boolean',
            'backwashed_sand_filter' => 'nullable|boolean',
            'adjusted_water_level' => 'nullable|boolean',
            'adjusted_auto_fill' => 'nullable|boolean',
            'adjusted_pump_timer' => 'nullable|boolean',
            'adjusted_heater' => 'nullable|boolean',
            'checked_cover' => 'nullable|boolean',
            'checked_lights' => 'nullable|boolean',
            'checked_fountain' => 'nullable|boolean',
            'checked_heater' => 'nullable|boolean',
            // Chemicals/services
            'chemicals_used' => 'nullable|string',
            'chemicals_cost' => 'nullable|numeric',
            'other_services' => 'nullable|string',
            'other_services_cost' => 'nullable|numeric',
            'total_cost' => 'nullable|numeric',
            // Notes/photos
            'notes_to_client' => 'nullable|string',
            'notes_to_admin' => 'nullable|string',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        $validated['technician_id'] = $user->id;
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
        if ($request->hasFile('photos')) {
            $photoPaths = [];
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('reports/photos', 'public');
                $photoPaths[] = $path;
            }
            $validated['photos'] = $photoPaths;
        } else {
            $validated['photos'] = [];
        }
        
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
        
        return redirect()->route('reports.show', $report)->with('success', 'Report submitted.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $user = Auth::user();
        if ($user->role === 'admin' || $user->role === 'technician') {
            $report = Report::with(['client', 'location', 'technician', 'invoice'])->findOrFail($id);
            return view('reports.show', compact('report'));
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
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!($user->role === 'admin' || $user->role === 'technician')) {
            abort(403);
        }

        $report = Report::findOrFail($id);
        
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'location_id' => 'required|exists:locations,id',
            'service_date' => 'required|date',
            'service_time' => 'required',
            'pool_gallons' => 'nullable|integer',
            // Chemistry
            'fac' => 'nullable|numeric',
            'cc' => 'nullable|numeric',
            'ph' => 'nullable|numeric',
            'alkalinity' => 'nullable|integer',
            'calcium' => 'nullable|integer',
            'salt' => 'nullable|integer',
            'cya' => 'nullable|integer',
            'tds' => 'nullable|integer',
            // Cleaning
            'vacuumed' => 'nullable|boolean',
            'brushed' => 'nullable|boolean',
            'skimmed' => 'nullable|boolean',
            'cleaned_skimmer_basket' => 'nullable|boolean',
            'cleaned_pump_basket' => 'nullable|boolean',
            'cleaned_pool_deck' => 'nullable|boolean',
            // Maintenance
            'cleaned_filter_cartridge' => 'nullable|boolean',
            'backwashed_sand_filter' => 'nullable|boolean',
            'adjusted_water_level' => 'nullable|boolean',
            'adjusted_auto_fill' => 'nullable|boolean',
            'adjusted_pump_timer' => 'nullable|boolean',
            'adjusted_heater' => 'nullable|boolean',
            'checked_cover' => 'nullable|boolean',
            'checked_lights' => 'nullable|boolean',
            'checked_fountain' => 'nullable|boolean',
            'checked_heater' => 'nullable|boolean',
            // Chemicals/services
            'chemicals_used' => 'nullable|string',
            'chemicals_cost' => 'nullable|numeric',
            'other_services' => 'nullable|string',
            'other_services_cost' => 'nullable|numeric',
            'total_cost' => 'nullable|numeric',
            // Notes/photos
            'notes_to_client' => 'nullable|string',
            'notes_to_admin' => 'nullable|string',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

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
        if ($request->hasFile('photos')) {
            // Delete old photos from storage
            if ($report->photos) {
                foreach ($report->photos as $oldPhoto) {
                    \Storage::disk('public')->delete($oldPhoto);
                }
            }
            $photoPaths = [];
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('reports/photos', 'public');
                $photoPaths[] = $path;
            }
            $validated['photos'] = $photoPaths;
        }
        
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
