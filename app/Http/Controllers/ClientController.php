<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    /**
     * Display a listing of clients.
     */
    public function index(Request $request)
    {
        $query = Client::query();

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by active status
        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
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
                $query->orderBy('last_name', 'asc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $clients = $query->paginate(15);

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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email',
            'phone' => 'nullable|string|max:20',
            'street_address' => 'nullable|string|max:255',
            'street_address_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:2',
            'zip_code' => 'nullable|string|max:10',
            'notes_by_client' => 'nullable|string',
            'notes_by_admin' => 'nullable|string',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'role' => ['required', Rule::in(['client', 'tech', 'admin'])],
            'appointment_reminders' => 'boolean',
            'mailing_list' => 'boolean',
            'monthly_billing' => 'boolean',
            'service_reports' => ['required', Rule::in(['full', 'invoice_only', 'none'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_active' => 'boolean',
        ]);

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->store('clients/photos', 'public');
            $validated['profile_photo'] = $path;
        }

        $client = Client::create($validated);

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
    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('clients')->ignore($client->id)],
            'phone' => 'nullable|string|max:20',
            'street_address' => 'nullable|string|max:255',
            'street_address_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:2',
            'zip_code' => 'nullable|string|max:10',
            'notes_by_client' => 'nullable|string',
            'notes_by_admin' => 'nullable|string',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'role' => ['required', Rule::in(['client', 'tech', 'admin'])],
            'appointment_reminders' => 'boolean',
            'mailing_list' => 'boolean',
            'monthly_billing' => 'boolean',
            'service_reports' => ['required', Rule::in(['full', 'invoice_only', 'none'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_active' => 'boolean',
        ]);

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            // Delete old photo if exists
            if ($client->profile_photo) {
                Storage::disk('public')->delete($client->profile_photo);
            }
            $path = $request->file('profile_photo')->store('clients/photos', 'public');
            $validated['profile_photo'] = $path;
        }

        $client->update($validated);

        // Log activity
        Activity::log('update', "Updated client: {$client->full_name}", auth()->user(), $client);

        return redirect()->route('clients.show', $client)
                        ->with('success', 'Client updated successfully.');
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
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $clients = $query->get();

        $filename = 'clients_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function() use ($clients) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Address', 'City', 'State', 'Zip',
                'Status', 'Active', 'Role', 'Created At'
            ]);

            foreach ($clients as $client) {
                fputcsv($file, [
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
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
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
        
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $clients = Client::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%{$query}%")
                  ->orWhere('last_name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            })
            ->limit(10)
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