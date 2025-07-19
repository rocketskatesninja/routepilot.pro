<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use App\Models\Activity;
use App\Http\Requests\ClientRequest;
use App\Services\PhotoUploadService;
use App\Traits\HasSearchable;
use App\Traits\HasSortable;
use App\Traits\HasExportable;
use App\Constants\AppConstants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class ClientController extends Controller
{
    use HasSearchable, HasSortable, HasExportable;

    protected $photoUploadService;

    public function __construct(PhotoUploadService $photoUploadService)
    {
        $this->photoUploadService = $photoUploadService;
    }
    /**
     * Display a listing of clients.
     */
    public function index(Request $request)
    {
        $query = Client::query();

        // Apply search
        $searchTerm = $this->getSearchTerm($request);
        $this->applySearch($query, ['first_name', 'last_name', 'email', 'phone'], $searchTerm);

        // Apply filters
        $this->applyFilters($query, $request, [
            'status' => ['type' => 'string'],
            'active' => ['type' => 'boolean', 'column' => 'is_active'],
        ]);

        // Apply sorting
        $sortOptions = [
            'date_desc' => ['column' => 'created_at', 'direction' => 'desc'],
            'date_asc' => ['column' => 'created_at', 'direction' => 'asc'],
            'status' => ['column' => 'status', 'direction' => 'asc'],
            'name' => ['column' => 'last_name', 'direction' => 'asc'],
        ];
        $this->applySorting($query, $sortOptions, 'created_at');

        $clients = $query->paginate(AppConstants::DEFAULT_PAGINATION);

        // Get statistics
        $stats = [
            'total' => Client::count(),
            'active' => Client::where('is_active', true)->count(),
            'inactive' => Client::where('is_active', false)->count(),
        ];

        return view('clients.index', compact('clients', 'stats'));
    }

    /**
     * Show the form for creating a new client.
     */
    public function create()
    {
        return view('clients.create');
    }

    /**
     * Store a newly created client in storage.
     */
    public function store(ClientRequest $request)
    {
        $validated = $request->validated();

        // Handle service reports logic
        if (!$validated['service_reports_enabled']) {
            $validated['service_reports'] = 'none';
        }
        unset($validated['service_reports_enabled']);

        // Handle profile photo upload
        $validated['profile_photo'] = $this->photoUploadService->handleSinglePhotoUpload(
            $request, 
            'clients/photos'
        );

        // Handle password creation if provided
        $password = null;
        if (!empty($validated['password'])) {
            $password = $validated['password'];
        }
        unset($validated['password']); // Remove password from client creation

        $client = Client::create($validated);

        // Create corresponding user account if password was provided
        if ($password) {
            User::create([
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'street_address' => $client->street_address,
                'street_address_2' => $client->street_address_2,
                'city' => $client->city,
                'state' => $client->state,
                'zip_code' => $client->zip_code,
                'role' => 'customer',
                'password' => Hash::make($password),
                'appointment_reminders' => $client->appointment_reminders,
                'mailing_list' => $client->mailing_list,
                'monthly_billing' => $client->monthly_billing,
                'service_reports' => $client->service_reports,
                'is_active' => $client->is_active,
            ]);
        }

        // If requested, create a location using the client's address
        if ($request->has('create_first_location')) {
            $locationData = [
                'client_id' => $client->id,
                'nickname' => $client->street_address,
                'street_address' => $client->street_address,
                'street_address_2' => $client->street_address_2,
                'city' => $client->city,
                'state' => $client->state,
                'zip_code' => $client->zip_code,
                // All other fields will be null by default
            ];
            \App\Models\Location::create($locationData);
        }

        // Log activity
        Activity::log('create', "Created new client: {$client->full_name}", auth()->user(), $client);

        return redirect()->route('clients.show', $client)
                        ->with('success', 'Client created successfully.');
    }

    /**
     * Display the specified client.
     */
    public function show(Client $client)
    {
        $client->load(['locations', 'invoices', 'reports']);
        
        // Get recent activities for this client
        $recentActivities = Activity::where('model_type', Client::class)
                                  ->where('model_id', $client->id)
                                  ->latest()
                                  ->take(10)
                                  ->get();

        return view('clients.show', compact('client', 'recentActivities'));
    }

    /**
     * Show the form for editing the specified client.
     */
    public function edit(Client $client)
    {
        return view('clients.edit', compact('client'));
    }

    /**
     * Update the specified client in storage.
     */
    public function update(ClientRequest $request, Client $client)
    {
        $validated = $request->validated();

        // Handle service reports logic
        if (!$validated['service_reports_enabled']) {
            $validated['service_reports'] = 'none';
        }
        unset($validated['service_reports_enabled']);

        // Handle profile photo upload
        $validated['profile_photo'] = $this->photoUploadService->handleSinglePhotoUpload(
            $request, 
            'clients/photos',
            $client->profile_photo
        );

        // Handle password update if provided
        $passwordUpdated = false;
        if (!empty($validated['password'])) {
            // Find the corresponding user record
            $user = User::where('email', $client->email)->first();
            if ($user) {
                $user->update([
                    'password' => Hash::make($validated['password'])
                ]);
                $passwordUpdated = true;
            } else {
                // Create a user account for this client
                User::create([
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'street_address' => $client->street_address,
                    'street_address_2' => $client->street_address_2,
                    'city' => $client->city,
                    'state' => $client->state,
                    'zip_code' => $client->zip_code,
                    'role' => 'customer',
                    'password' => Hash::make($validated['password']),
                    'appointment_reminders' => $client->appointment_reminders,
                    'mailing_list' => $client->mailing_list,
                    'monthly_billing' => $client->monthly_billing,
                    'service_reports' => $client->service_reports,
                    'is_active' => $client->is_active,
                ]);
                $passwordUpdated = true;
            }
        }
        unset($validated['password']); // Remove password from client update

        $client->update($validated);

        // Log activity
        Activity::log('update', "Updated client: {$client->full_name}", auth()->user(), $client);

        $message = 'Client updated successfully.';
        if ($passwordUpdated) {
            $message .= ' Password has been updated.';
        }

        return redirect()->route('clients.show', $client)
                        ->with('success', $message);
    }

    /**
     * Remove the specified client from storage.
     */
    public function destroy(Client $client)
    {
        // Check if client has related data
        if ($client->locations()->count() > 0 || $client->invoices()->count() > 0) {
            return back()->with('error', 'Cannot delete client with existing locations or invoices.');
        }

        // Delete profile photo if exists
        if ($client->profile_photo) {
            Storage::disk('public')->delete($client->profile_photo);
        }

        $clientName = $client->full_name;
        $client->delete();

        // Log activity
        Activity::log('delete', "Deleted client: {$clientName}", auth()->user());

        return redirect()->route('clients.index')
                        ->with('success', 'Client deleted successfully.');
    }

    /**
     * Export clients to CSV.
     */
    public function export(Request $request)
    {
        $query = Client::query();

        // Apply filters
        $this->applyFilters($query, $request, [
            'status' => ['type' => 'string'],
            'active' => ['type' => 'boolean', 'column' => 'is_active'],
        ]);

        $clients = $query->get();

        $headers = [
            'ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Address', 'City', 'State', 'Zip',
            'Status', 'Active', 'Role', 'Created At'
        ];

        $data = $clients->map(function ($client) {
            return [
                $client->id,
                $client->first_name,
                $client->last_name,
                $client->email,
                $client->phone,
                $client->street_address,
                $client->city,
                $client->state,
                $client->zip_code,
                $client->status,
                $client->is_active ? 'Yes' : 'No',
                $client->role,
                $client->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return $this->exportToCsv($data, $headers, 'clients');
    }

    /**
     * Toggle client active status.
     */
    public function toggleStatus(Client $client)
    {
        $client->update(['is_active' => !$client->is_active]);
        
        $status = $client->is_active ? 'activated' : 'deactivated';
        Activity::log('update', "{$status} client: {$client->full_name}", auth()->user(), $client);

        return back()->with('success', "Client {$status} successfully.");
    }

    /**
     * Search clients for autocomplete.
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        
        if (strlen($query) < 1) {
            return response()->json([]);
        }
        $queryLower = strtolower($query);
        $clients = Client::where('is_active', true)
            ->where(function ($q) use ($queryLower) {
                $q->whereRaw('LOWER(first_name) LIKE ?', ["{$queryLower}%"])
                  ->orWhereRaw('LOWER(last_name) LIKE ?', ["{$queryLower}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["{$queryLower}%"]);
            })
            ->limit(AppConstants::SEARCH_RESULT_LIMIT)
            ->get(['id', 'first_name', 'last_name', 'email'])
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'full_name' => $client->full_name,
                    'email' => $client->email,
                ];
            });

        return response()->json($clients);
    }

    /**
     * Get locations for a specific client.
     */
    public function getLocations(Client $client)
    {
        $locations = $client->locations()
            ->where('status', 'active')
            ->get(['id', 'nickname', 'city', 'state'])
            ->map(function ($location) {
                return [
                    'id' => $location->id,
                    'name' => $location->nickname,
                    'city' => $location->city,
                    'state' => $location->state,
                    'display_name' => "{$location->nickname} - {$location->city}, {$location->state}",
                ];
            });

        return response()->json($locations);
    }
} 