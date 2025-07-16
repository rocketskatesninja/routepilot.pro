<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Client;
use App\Models\User;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    /**
     * Display a listing of locations.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        if ($user->role === 'admin' || $user->role === 'technician') {
            $query = Location::with(['client', 'assignedTechnician']);
        } elseif ($user->role === 'client') {
            $query = $user->locations()->with(['client', 'assignedTechnician']);
        } else {
            abort(403);
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nickname', 'like', "%{$search}%")
                  ->orWhere('street_address', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhereHas('client', function ($clientQuery) use ($search) {
                      $clientQuery->where('first_name', 'like', "%{$search}%")
                                 ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by pool type
        if ($request->filled('pool_type')) {
            $query->where('pool_type', $request->pool_type);
        }

        // Filter by water type
        if ($request->filled('water_type')) {
            $query->where('water_type', $request->water_type);
        }

        // Sort functionality
        $sortBy = $request->get('sort_by', 'date_desc');
        
        switch ($sortBy) {
            case 'date_desc':
                $query->orderBy('created_at', 'desc');
                break;
            case 'date_asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'status':
                $query->orderBy('status', 'asc');
                break;
            case 'name':
                $query->orderBy('nickname', 'asc');
                break;
            default:
                $query->orderBy('nickname', 'asc');
                break;
        }

        $locations = $query->paginate(15);
        
        $stats = [
            'total' => $locations->total(),
            'active' => $locations->where('status', 'active')->count(),
            'favorite' => $locations->where('is_favorite', true)->count(),
            'residential' => $locations->where('access', 'residential')->count(),
            'commercial' => $locations->where('access', 'commercial')->count(),
        ];
        
        $clients = \App\Models\Client::orderBy('last_name')->get();
        return view('locations.index', compact('locations', 'stats', 'clients'));
    }

    /**
     * Show the form for creating a new location.
     */
    public function create(Request $request)
    {
        $clients = Client::orderBy('first_name')->get();
        $technicians = User::where('role', 'technician')->orderBy('first_name')->get();
        $selectedClientId = $request->get('client_id');
        
        return view('locations.create', compact('clients', 'technicians', 'selectedClientId'));
    }

    /**
     * Store a newly created location in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'nickname' => 'nullable|string|max:255',
            'street_address' => 'required|string|max:255',
            'street_address_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:2',
            'zip_code' => 'required|string|max:10',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'access' => ['required', Rule::in(['residential', 'commercial'])],
            'pool_type' => ['nullable', Rule::in(['fiberglass', 'vinyl_liner', 'concrete', 'gunite'])],
            'water_type' => ['required', Rule::in(['chlorine', 'salt'])],
            'filter_type' => 'nullable|string|max:255',
            'setting' => ['required', Rule::in(['indoor', 'outdoor'])],
            'installation' => ['required', Rule::in(['inground', 'above'])],
            'gallons' => 'nullable|integer|min:1',
            'service_frequency' => ['required', Rule::in(['weekly', 'bi-weekly', 'monthly', 'as-needed'])],
            'service_day_1' => 'nullable|string|max:255',
            'service_day_2' => 'nullable|string|max:255',
            'rate_per_visit' => 'nullable|numeric|min:0',
            'chemicals_included' => 'boolean',
            'assigned_technician_id' => 'nullable|exists:users,id',
            'is_favorite' => 'boolean',
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => 'nullable|string',
            // Cleaning tasks
            'vacuumed' => 'boolean',
            'brushed' => 'boolean',
            'skimmed' => 'boolean',
            'cleaned_skimmer_basket' => 'boolean',
            'cleaned_pump_basket' => 'boolean',
            'cleaned_pool_deck' => 'boolean',
            // Maintenance tasks
            'cleaned_filter_cartridge' => 'boolean',
            'backwashed_sand_filter' => 'boolean',
            'adjusted_water_level' => 'boolean',
            'adjusted_auto_fill' => 'boolean',
            'adjusted_pump_timer' => 'boolean',
            'adjusted_heater' => 'boolean',
            'checked_cover' => 'boolean',
            'checked_lights' => 'boolean',
            'checked_fountain' => 'boolean',
            'checked_heater' => 'boolean',
            // Other services
            'other_services' => 'nullable|string',
            'other_services_cost' => 'nullable|numeric|min:0',
        ]);

        // Handle photo uploads
        if ($request->hasFile('photos')) {
            \Log::info('Photo upload detected', ['count' => count($request->file('photos'))]);
            $photoPaths = [];
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('locations/photos', 'public');
                $photoPaths[] = $path;
                \Log::info('Photo stored', ['path' => $path]);
            }
            $validated['photos'] = $photoPaths;
        } else {
            \Log::info('No photos uploaded');
        }

        // Ensure numeric fields are not null
        $validated['other_services_cost'] = $validated['other_services_cost'] ?? 0;
        $validated['rate_per_visit'] = $validated['rate_per_visit'] ?? null;
        $validated['gallons'] = $validated['gallons'] ?? null;

        $location = Location::create($validated);

        // Log activity
        $locationName = $location->nickname ?: $location->street_address;
        Activity::log('create', "Created new location: {$locationName}", auth()->user(), $location);

        return redirect()->route('locations.show', $location)
                        ->with('success', 'Location created successfully.');
    }

    /**
     * Display the specified location.
     */
    public function show(Location $location)
    {
        $location->load(['client', 'assignedTechnician', 'invoices', 'reports']);
        
        // Get recent activities for this location
        $recentActivities = Activity::where('model_type', Location::class)
                                  ->where('model_id', $location->id)
                                  ->latest()
                                  ->take(10)
                                  ->get();

        return view('locations.show', compact('location', 'recentActivities'));
    }

    /**
     * Return location details as JSON for API consumption.
     */
    public function showApi(Location $location)
    {
        $location->load(['client', 'assignedTechnician']);
        
        return response()->json([
            'id' => $location->id,
            'nickname' => $location->nickname,
            'street_address' => $location->street_address,
            'city' => $location->city,
            'state' => $location->state,
            'zip_code' => $location->zip_code,
            'client' => [
                'id' => $location->client->id,
                'full_name' => $location->client->full_name,
                'email' => $location->client->email,
            ],
            'assigned_technician' => $location->assignedTechnician ? [
                'id' => $location->assignedTechnician->id,
                'full_name' => $location->assignedTechnician->full_name,
                'email' => $location->assignedTechnician->email,
            ] : null,
        ]);
    }

    /**
     * Show the form for editing the specified location.
     */
    public function edit(Location $location)
    {
        $clients = Client::orderBy('first_name')->get();
        $technicians = User::where('role', 'technician')->orderBy('first_name')->get();
        
        return view('locations.edit', compact('location', 'clients', 'technicians'));
    }

    /**
     * Update the specified location in storage.
     */
    public function update(Request $request, Location $location)
    {
        \Log::info('Location update request data', $request->all());
        
        try {
            $validated = $request->validate([
                'client_id' => 'required|exists:clients,id',
                'nickname' => 'nullable|string|max:255',
                'street_address' => 'required|string|max:255',
                'street_address_2' => 'nullable|string|max:255',
                'city' => 'required|string|max:255',
                'state' => 'required|string|max:2',
                'zip_code' => 'required|string|max:10',
                'photos' => 'nullable|array',
                'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
                'access' => ['required', Rule::in(['residential', 'commercial'])],
                'pool_type' => ['nullable', Rule::in(['fiberglass', 'vinyl_liner', 'concrete', 'gunite'])],
                'water_type' => ['required', Rule::in(['chlorine', 'salt'])],
                'filter_type' => 'nullable|string|max:255',
                'setting' => ['required', Rule::in(['indoor', 'outdoor'])],
                'installation' => ['required', Rule::in(['inground', 'above'])],
                'gallons' => 'nullable|integer|min:1',
                'service_frequency' => ['required', Rule::in(['weekly', 'bi-weekly', 'monthly', 'as-needed'])],
                'service_day_1' => 'nullable|string|max:255',
                'service_day_2' => 'nullable|string|max:255',
                'rate_per_visit' => 'nullable|numeric|min:0',
                'chemicals_included' => 'boolean',
                'assigned_technician_id' => 'nullable|exists:users,id',
                'is_favorite' => 'boolean',
                'status' => ['required', Rule::in(['active', 'inactive'])],
                'notes' => 'nullable|string',
                // Cleaning tasks
                'vacuumed' => 'boolean',
                'brushed' => 'boolean',
                'skimmed' => 'boolean',
                'cleaned_skimmer_basket' => 'boolean',
                'cleaned_pump_basket' => 'boolean',
                'cleaned_pool_deck' => 'boolean',
                // Maintenance tasks
                'cleaned_filter_cartridge' => 'boolean',
                'backwashed_sand_filter' => 'boolean',
                'adjusted_water_level' => 'boolean',
                'adjusted_auto_fill' => 'boolean',
                'adjusted_pump_timer' => 'boolean',
                'adjusted_heater' => 'boolean',
                'checked_cover' => 'boolean',
                'checked_lights' => 'boolean',
                'checked_fountain' => 'boolean',
                'checked_heater' => 'boolean',
                // Other services
                'other_services' => 'nullable|string',
                'other_services_cost' => 'nullable|numeric|min:0',
            ]);
            \Log::info('Location update validated data', $validated);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Location update validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            throw $e;
        }

        // Handle photo uploads
        if ($request->hasFile('photos')) {
            // Delete old photos from storage
            if ($location->photos) {
                foreach ($location->photos as $oldPhoto) {
                    \Storage::disk('public')->delete($oldPhoto);
                }
            }
            $photoPaths = [];
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('locations/photos', 'public');
                $photoPaths[] = $path;
                \Log::info('Photo stored in update', ['path' => $path]);
            }
            $validated['photos'] = $photoPaths;
        } else {
            \Log::info('No photos uploaded in update');
            // Keep existing photos if no new ones are uploaded
            $validated['photos'] = $location->photos;
        }

        // Ensure numeric fields are not null
        $validated['other_services_cost'] = $validated['other_services_cost'] ?? 0;
        $validated['rate_per_visit'] = $validated['rate_per_visit'] ?? null;
        $validated['gallons'] = $validated['gallons'] ?? null;

        \Log::info('About to update location', ['location_id' => $location->id, 'data_to_update' => $validated]);
        $location->update($validated);
        \Log::info('Location updated successfully', ['location_id' => $location->id]);

        // Log activity
        $locationName = $location->nickname ?: $location->street_address;
        Activity::log('update', "Updated location: {$locationName}", auth()->user(), $location);

        return redirect()->route('locations.show', $location)
                        ->with('success', 'Location updated successfully.');
    }

    /**
     * Remove the specified location from storage.
     */
    public function destroy(Location $location)
    {
        // Check if location has related data
        if ($location->invoices()->count() > 0 || $location->reports()->count() > 0) {
            return back()->with('error', 'Cannot delete location with existing invoices or reports.');
        }

        // Delete photos if exist
        if ($location->photos) {
            foreach ($location->photos as $photo) {
                Storage::disk('public')->delete($photo);
            }
        }

        $locationName = $location->nickname ?: $location->street_address;
        $location->delete();

        // Log activity
        Activity::log('delete', "Deleted location: {$locationName}", auth()->user());

        return redirect()->route('locations.index')
                        ->with('success', 'Location deleted successfully.');
    }

    /**
     * Export locations to CSV.
     */
    public function export(Request $request)
    {
        $query = Location::with(['client', 'assignedTechnician']);

        // Apply filters
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('pool_type')) {
            $query->where('pool_type', $request->pool_type);
        }

        $locations = $query->get();

        $filename = 'locations_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function() use ($locations) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'ID', 'Client', 'Nickname', 'Address', 'City', 'State', 'Zip',
                'Pool Type', 'Water Type', 'Gallons', 'Status', 'Technician', 'Created At'
            ]);

            foreach ($locations as $location) {
                fputcsv($file, [
                    $location->id,
                    $location->client->full_name,
                    $location->nickname,
                    $location->street_address,
                    $location->city,
                    $location->state,
                    $location->zip_code,
                    $location->pool_type ?? 'Unknown',
                    $location->water_type,
                    $location->gallons,
                    $location->status,
                    $location->assignedTechnician ? $location->assignedTechnician->full_name : 'Unassigned',
                    $location->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Toggle location favorite status.
     */
    public function toggleFavorite(Location $location)
    {
        $location->update(['is_favorite' => !$location->is_favorite]);
        
        $status = $location->is_favorite ? 'favorited' : 'unfavorited';
        $locationName = $location->nickname ?: $location->street_address;
        Activity::log('update', "{$status} location: {$locationName}", auth()->user(), $location);

        return back()->with('success', "Location {$status} successfully.");
    }

    /**
     * Toggle location active status.
     */
    public function toggleStatus(Location $location)
    {
        $location->update(['status' => $location->status === 'active' ? 'inactive' : 'active']);
        
        $status = $location->status === 'active' ? 'activated' : 'deactivated';
        $locationName = $location->nickname ?: $location->street_address;
        Activity::log('update', "{$status} location: {$locationName}", auth()->user(), $location);

        return back()->with('success', "Location {$status} successfully.");
    }
} 