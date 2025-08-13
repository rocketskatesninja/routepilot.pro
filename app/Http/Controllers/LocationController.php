<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Client;
use App\Models\User;
use App\Models\Activity;
use App\Http\Requests\LocationRequest;
use App\Services\PhotoUploadService;
use App\Traits\HasSearchable;
use App\Traits\HasSortable;
use App\Traits\HasExportable;
use App\Constants\AppConstants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LocationController extends Controller
{
    use HasSearchable, HasSortable, HasExportable;

    protected $photoUploadService;

    public function __construct(PhotoUploadService $photoUploadService)
    {
        $this->photoUploadService = $photoUploadService;
    }
    /**
     * Display a listing of locations.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        if ($user->role === AppConstants::ROLE_ADMIN || $user->role === AppConstants::ROLE_TECHNICIAN) {
            $query = Location::with(['client', 'assignedTechnician']);
        } elseif ($user->role === AppConstants::ROLE_CLIENT) {
            // For customers, get locations through their client record
            $client = Client::where('email', $user->email)->first();
            if ($client) {
                $query = Location::where('client_id', $client->id)->with(['client', 'assignedTechnician']);
            } else {
                $query = Location::where('id', 0); // Empty query if no client found
            }
        } else {
            abort(403);
        }

        // Apply search
        $searchTerm = $this->getSearchTerm($request);
        $this->applySearch($query, ['nickname', 'street_address', 'city', 'client.first_name', 'client.last_name'], $searchTerm);

        // Apply filters
        $this->applyFilters($query, $request, [
            'status' => ['type' => 'string'],
            'pool_type' => ['type' => 'string'],
            'water_type' => ['type' => 'string'],
        ]);

        // Apply sorting
        $sortOptions = [
            'date_desc' => ['column' => 'created_at', 'direction' => 'desc'],
            'date_asc' => ['column' => 'created_at', 'direction' => 'asc'],
            'status' => ['column' => 'status', 'direction' => 'asc'],
            'name' => ['column' => 'nickname', 'direction' => 'asc'],
        ];
        $this->applySorting($query, $sortOptions, 'nickname');

        $locations = $query->paginate(AppConstants::DEFAULT_PAGINATION);
        
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
    public function store(LocationRequest $request)
    {
        $validated = $request->validated();

        // Handle photo uploads
        $validated['photos'] = $this->photoUploadService->handlePhotoUploads(
            $request, 
            'locations/photos'
        );

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
        $user = auth()->user();
        
        // Check if customer can view this location
        if ($user->role === AppConstants::ROLE_CLIENT) {
            $client = Client::where('email', $user->email)->first();
            if (!$client || $location->client_id !== $client->id) {
                abort(403, 'You can only view your own locations.');
            }
        }
        
        $location->load(['client', 'assignedTechnician', 'reports']);
        
        // Load invoices, filtering out drafts for customers
        if ($user->role === AppConstants::ROLE_CLIENT) {
            $location->setRelation('invoices', $location->invoices()->where('status', '!=', 'draft')->get());
        } else {
            $location->load('invoices');
        }
        
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
            'chemicals_included' => $location->chemicals_included,
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
    public function update(LocationRequest $request, Location $location)
    {
        $validated = $request->validated();

        // Handle photo uploads
        $validated['photos'] = $this->photoUploadService->handlePhotoUploads(
            $request, 
            'locations/photos',
            $location->photos ?? []
        );

        // Ensure numeric fields are not null
        $validated['other_services_cost'] = $validated['other_services_cost'] ?? 0;
        $validated['rate_per_visit'] = $validated['rate_per_visit'] ?? null;
        $validated['gallons'] = $validated['gallons'] ?? null;

        $location->update($validated);

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
        $this->applyFilters($query, $request, [
            'client_id' => ['type' => 'integer'],
            'status' => ['type' => 'string'],
            'pool_type' => ['type' => 'string'],
        ]);

        $locations = $query->get();

        $headers = [
            'ID', 'Client', 'Nickname', 'Address', 'City', 'State', 'Zip',
            'Pool Type', 'Water Type', 'Gallons', 'Status', 'Technician', 'Created At'
        ];

        $data = $locations->map(function ($location) {
            return [
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
            ];
        });

        return $this->exportToCsv($data, $headers, 'locations');
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

    /**
     * Delete a single photo from a location (AJAX).
     */
    public function deletePhoto(Request $request, Location $location)
    {
        try {
            $user = auth()->user();
            
            // Check authorization based on user role and location access
            if ($user->role === AppConstants::ROLE_ADMIN || $user->role === AppConstants::ROLE_TECHNICIAN) {
                // Admin and technicians can delete photos from any location
            } elseif ($user->role === AppConstants::ROLE_CLIENT) {
                // Clients can only delete photos from their own locations
                $client = Client::where('email', $user->email)->first();
                if (!$client || $location->client_id !== $client->id) {
                    return response()->json(['error' => 'Unauthorized'], 403);
                }
            } else {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            
            $photo = $request->input('photo');
            if (!$photo || !is_array($location->photos) || !in_array($photo, $location->photos)) {
                return response()->json(['error' => 'Photo not found'], 404);
            }
            
            // Check if file exists before attempting deletion
            if (!Storage::disk('public')->exists($photo)) {
                \Log::warning("Location photo file not found in storage: {$photo}");
            }
            
            // Remove from array
            $updatedPhotos = array_values(array_filter($location->photos, fn($p) => $p !== $photo));
            $location->photos = $updatedPhotos;
            $location->save();
            
            // Delete from storage
            $deleted = Storage::disk('public')->delete($photo);
            
            if (!$deleted) {
                \Log::warning("Failed to delete location photo from storage: {$photo}");
            } else {
                \Log::info("Successfully deleted location photo from storage: {$photo}");
            }
            
            // Log activity
            $locationName = $location->nickname ?: $location->street_address;
            Activity::log('delete', "Deleted photo from location: {$locationName}", $user, $location);
            
            return response()->json(['success' => true, 'message' => 'Location photo deleted successfully']);
            
        } catch (\Exception $e) {
            \Log::error("Error deleting location photo: " . $e->getMessage());
            return response()->json(['error' => 'Failed to delete location photo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Show a specific location on a dedicated map page.
     */
    public function showOnMap(Location $location)
    {
        // Check authorization
        if (!auth()->user()->isAdmin() && !auth()->user()->isTechnician()) {
            abort(403, 'Unauthorized action.');
        }

        return view('map.location', compact('location'));
    }
} 