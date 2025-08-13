<?php

namespace App\Http\Controllers;

use App\Constants\AppConstants;
use App\Exceptions\TechnicianException;
use App\Http\Requests\TechnicianRequest;
use App\Models\User;
use App\Services\LoggingService;
use App\Services\PhotoUploadService;
use App\Traits\HasExportable;
use App\Traits\HasSearchable;
use App\Traits\HasSortable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class TechnicianController extends Controller
{
    use HasSearchable, HasSortable, HasExportable;



    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            // Check if the authenticated user has permission to access technician data
            $user = auth()->user();
            if (!$user || !in_array($user->role, [AppConstants::ROLE_ADMIN, AppConstants::ROLE_TECHNICIAN])) {
                LoggingService::logError('Unauthorized technician access attempt', [
                    'user_id' => $user?->id,
                    'user_role' => $user?->role,
                    'route' => 'technicians.index',
                    'ip' => $request->ip(),
                ]);
                
                return redirect()->route('dashboard')
                    ->with('error', 'You do not have permission to access technician management.');
            }
            
            $query = User::where('role', AppConstants::ROLE_TECHNICIAN);

            // Apply search functionality using trait
            $searchFields = ['first_name', 'last_name', 'email', 'phone'];
            $searchTerm = $this->getSearchTerm($request);
            $query = $this->applySearch($query, $searchFields, $searchTerm);

            // Apply filters
            $filterFields = [
                'status' => [
                    'type' => 'boolean',
                    'column' => 'is_active',
                    'operator' => '=',
                ],
            ];
            $this->applyFilters($query, $request, $filterFields);

            // Apply sorting using trait
            $sortOptions = [
                'date_desc' => ['column' => 'created_at', 'direction' => 'desc'],
                'date_asc' => ['column' => 'created_at', 'direction' => 'asc'],
                'status' => ['column' => 'is_active', 'direction' => 'desc'],
                'name' => ['column' => 'last_name', 'direction' => 'asc'],
            ];
            $query = $this->applySorting($query, $sortOptions, 'created_at');

            $technicians = $query->paginate(AppConstants::DEFAULT_PAGINATION);

            LoggingService::logUserAction('viewed technicians list', [
                'count' => $technicians->count(),
                'filters' => $request->only(['search', 'status', 'sort_by']),
            ]);

            return view('technicians.index', compact('technicians'));
        } catch (\Exception $e) {
            LoggingService::logError('Failed to load technicians list', [], $e);
            throw new TechnicianException('Failed to load technicians list: ' . $e->getMessage());
        }
    }

    /**
     * Get technicians with active location sharing for map display.
     */
    public function getTechniciansForMap()
    {
        try {
            $user = auth()->user();
            if (!$user || !in_array($user->role, [AppConstants::ROLE_ADMIN, AppConstants::ROLE_TECHNICIAN])) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Only get technicians with active location sharing and recent GPS data
            $technicians = User::where('role', AppConstants::ROLE_TECHNICIAN)
                ->where('location_sharing_enabled', true)
                ->whereNotNull('current_latitude')
                ->whereNotNull('current_longitude')
                ->where('location_updated_at', '>=', now()->subHours(24)) // Only show locations updated in last 24 hours
                ->where('is_active', true)
                ->select([
                    'id', 'first_name', 'last_name', 'email', 'phone', 'is_active',
                    'current_latitude', 'current_longitude', 'location_updated_at', 'location_sharing_enabled'
                ])
                ->get()
                ->map(function ($technician) {
                    return [
                        'id' => $technician->id,
                        'name' => $technician->full_name,
                        'email' => $technician->email,
                        'phone' => $technician->phone,
                        'status' => $technician->is_active ? 'active' : 'inactive',
                        'current_latitude' => $technician->current_latitude,
                        'current_longitude' => $technician->current_longitude,
                        'location_updated_at' => $technician->location_updated_at,
                        'location_sharing_enabled' => $technician->location_sharing_enabled,
                    ];
                });

            return response()->json(['technicians' => $technicians]);
        } catch (\Exception $e) {
            LoggingService::logError('Failed to get technicians for map', [], $e);
            return response()->json(['error' => 'Failed to load technician data'], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            // Check if the authenticated user has permission to access technician data
            $user = auth()->user();
            if (!$user || !in_array($user->role, [AppConstants::ROLE_ADMIN, AppConstants::ROLE_TECHNICIAN])) {
                LoggingService::logError('Unauthorized technician access attempt', [
                    'user_id' => $user?->id,
                    'user_role' => $user?->role,
                    'route' => 'technicians.create',
                    'ip' => request()->ip(),
                ]);
                
                return redirect()->route('dashboard')
                    ->with('error', 'You do not have permission to access technician management.');
            }
            
            LoggingService::logUserAction('accessed technician create form');

        return view('technicians.create');
        } catch (\Exception $e) {
            LoggingService::logError('Failed to load technician create form', [], $e);
            throw new TechnicianException('Failed to load technician create form: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TechnicianRequest $request)
    {
        try {
            // Check if the authenticated user has permission to access technician data
            $user = auth()->user();
            if (!$user || !in_array($user->role, [AppConstants::ROLE_ADMIN, AppConstants::ROLE_TECHNICIAN])) {
                LoggingService::logError('Unauthorized technician access attempt', [
                    'user_id' => $user?->id,
                    'user_role' => $user?->role,
                    'route' => 'technicians.store',
                    'ip' => $request->ip(),
                ]);
                
                return redirect()->route('dashboard')
                    ->with('error', 'You do not have permission to access technician management.');
            }
            
            $validated = $request->validated();

            // Handle profile photo upload using PhotoUploadService
            $photoUploadService = new PhotoUploadService();
            $validated['profile_photo'] = $photoUploadService->handleSinglePhotoUpload(
                $request, 
                'profile-photos', 
                null
            );

        // Set default values
        $validated['password'] = Hash::make($validated['password']);
            $validated['role'] = AppConstants::ROLE_TECHNICIAN;
        $validated['is_active'] = (bool) $validated['is_active'];

            $technician = User::create($validated);

            LoggingService::logUserAction('created technician', [
                'technician_id' => $technician->id,
                'email' => $technician->email,
            ], 'User', $technician->id);

        return redirect()->route('technicians.index')
            ->with('success', 'Technician created successfully.');
        } catch (\Exception $e) {
            LoggingService::logError('Failed to create technician', [
                'email' => $request->email,
            ], $e);
            throw new TechnicianException('Failed to create technician: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            // Check if the authenticated user has permission to access technician data
            $user = auth()->user();
            if (!$user || !in_array($user->role, [AppConstants::ROLE_ADMIN, AppConstants::ROLE_TECHNICIAN])) {
                LoggingService::logError('Unauthorized technician access attempt', [
                    'user_id' => $user?->id,
                    'user_role' => $user?->role,
                    'route' => 'technicians.show',
                    'ip' => request()->ip(),
                ]);
                
                return redirect()->route('dashboard')
                    ->with('error', 'You do not have permission to access technician management.');
            }
            
            // Check if the technician exists and is actually a technician
            $technician = User::where('role', AppConstants::ROLE_TECHNICIAN)->find($id);
            if (!$technician) {
                LoggingService::logError('Invalid technician access attempt', [
                    'requested_id' => $id,
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'route' => 'technicians.show',
                ]);
                
                return redirect()->route('technicians.index')
                    ->with('error', 'Technician not found. The requested technician does not exist or is not a valid technician.');
            }
        
        // Get assigned locations
            $assignedLocations = $technician->assignedLocations()->paginate(AppConstants::SEARCH_RESULT_LIMIT);
        
        // Get recent reports
        $recentReports = $technician->reports()->latest()->take(5)->get();
        
        // Get recent invoices
        $recentInvoices = $technician->invoices()->latest()->take(5)->get();
        
        // Get recent activities
        $recentActivities = $technician->activities()->latest()->take(10)->get();

            LoggingService::logUserAction('viewed technician details', [
                'technician_id' => $id,
            ], 'User', $id);

        return view('technicians.show', compact(
            'technician', 
            'assignedLocations', 
            'recentReports', 
            'recentInvoices', 
            'recentActivities'
        ));
        } catch (\Exception $e) {
            LoggingService::logError('Failed to load technician details', [
                'technician_id' => $id,
            ], $e);
            throw new TechnicianException('Failed to load technician details: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            // Check if the authenticated user has permission to access technician data
            $user = auth()->user();
            if (!$user || !in_array($user->role, [AppConstants::ROLE_ADMIN, AppConstants::ROLE_TECHNICIAN])) {
                LoggingService::logError('Unauthorized technician access attempt', [
                    'user_id' => $user?->id,
                    'user_role' => $user?->role,
                    'route' => 'technicians.edit',
                    'ip' => request()->ip(),
                ]);
                
                return redirect()->route('dashboard')
                    ->with('error', 'You do not have permission to access technician management.');
            }
            
            // Check if the technician exists and is actually a technician
            $technician = User::where('role', AppConstants::ROLE_TECHNICIAN)->find($id);
            if (!$technician) {
                LoggingService::logError('Invalid technician access attempt', [
                    'requested_id' => $id,
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'route' => 'technicians.edit',
                ]);
                
                return redirect()->route('technicians.index')
                    ->with('error', 'Technician not found. The requested technician does not exist or is not a valid technician.');
            }
            
            LoggingService::logUserAction('accessed technician edit form', [
                'technician_id' => $id,
            ], 'User', $id);

            return view('technicians.edit', compact('technician'));
        } catch (\Exception $e) {
            LoggingService::logError('Failed to load technician edit form', [
                'technician_id' => $id,
            ], $e);
            throw new TechnicianException('Failed to load technician edit form: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TechnicianRequest $request, string $id)
    {
        try {
            // Check if the authenticated user has permission to access technician data
            $user = auth()->user();
            if (!$user || !in_array($user->role, [AppConstants::ROLE_ADMIN, AppConstants::ROLE_TECHNICIAN])) {
                LoggingService::logError('Unauthorized technician access attempt', [
                    'user_id' => $user?->id,
                    'user_role' => $user?->role,
                    'route' => 'technicians.update',
                    'ip' => $request->ip(),
                ]);
                
                return redirect()->route('dashboard')
                    ->with('error', 'You do not have permission to access technician management.');
            }
            
            // Check if the technician exists and is actually a technician
            $technician = User::where('role', AppConstants::ROLE_TECHNICIAN)->find($id);
            if (!$technician) {
                LoggingService::logError('Invalid technician access attempt', [
                    'requested_id' => $id,
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'route' => 'technicians.update',
                ]);
                
                return redirect()->route('technicians.index')
                    ->with('error', 'Technician not found. The requested technician does not exist or is not a valid technician.');
            }
            $validated = $request->validated();

            // Handle profile photo upload using PhotoUploadService
            $photoUploadService = new PhotoUploadService();
            $validated['profile_photo'] = $photoUploadService->handleSinglePhotoUpload(
                $request, 
                'profile-photos', 
                $technician->profile_photo
            );

        // Update password only if provided
        if ($request->filled('password')) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

            $validated['role'] = AppConstants::ROLE_TECHNICIAN;
        $validated['is_active'] = (bool) $validated['is_active'];

        $technician->update($validated);

            LoggingService::logUserAction('updated technician', [
                'technician_id' => $technician->id,
                'email' => $technician->email,
            ], 'User', $technician->id);

        return redirect()->route('technicians.show', $technician)
            ->with('success', 'Technician updated successfully.');
        } catch (\Exception $e) {
            LoggingService::logError('Failed to update technician', [
                'technician_id' => $id,
                'email' => $request->email,
            ], $e);
            throw new TechnicianException('Failed to update technician: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            // Check if the authenticated user has permission to access technician data
            $user = auth()->user();
            if (!$user || !in_array($user->role, [AppConstants::ROLE_ADMIN, AppConstants::ROLE_TECHNICIAN])) {
                LoggingService::logError('Unauthorized technician access attempt', [
                    'user_id' => $user?->id,
                    'user_role' => $user?->role,
                    'route' => 'technicians.destroy',
                    'ip' => request()->ip(),
                ]);
                
                return redirect()->route('dashboard')
                    ->with('error', 'You do not have permission to access technician management.');
            }
            
            // Check if the technician exists and is actually a technician
            $technician = User::where('role', AppConstants::ROLE_TECHNICIAN)->find($id);
            if (!$technician) {
                LoggingService::logError('Invalid technician access attempt', [
                    'requested_id' => $id,
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'route' => 'technicians.destroy',
                ]);
                
                return redirect()->route('technicians.index')
                    ->with('error', 'Technician not found. The requested technician does not exist or is not a valid technician.');
            }
        
        // Delete profile photo if exists
        if ($technician->profile_photo) {
            Storage::disk('public')->delete($technician->profile_photo);
        }
        
        $technician->delete();

            LoggingService::logUserAction('deleted technician', [
                'technician_id' => $id,
                'email' => $technician->email,
            ], 'User', $id);

        return redirect()->route('technicians.index')
            ->with('success', 'Technician deleted successfully.');
        } catch (\Exception $e) {
            LoggingService::logError('Failed to delete technician', [
                'technician_id' => $id,
            ], $e);
            throw new TechnicianException('Failed to delete technician: ' . $e->getMessage());
        }
    }

    /**
     * Toggle technician status
     */
    public function toggleStatus(string $id)
    {
        try {
            // Check if the authenticated user has permission to access technician data
            $user = auth()->user();
            if (!$user || !in_array($user->role, [AppConstants::ROLE_ADMIN, AppConstants::ROLE_TECHNICIAN])) {
                LoggingService::logError('Unauthorized technician access attempt', [
                    'user_id' => $user?->id,
                    'user_role' => $user?->role,
                    'route' => 'technicians.toggle-status',
                    'ip' => request()->ip(),
                ]);
                
                return redirect()->route('dashboard')
                    ->with('error', 'You do not have permission to access technician management.');
            }
            
            // Check if the technician exists and is actually a technician
            $technician = User::where('role', AppConstants::ROLE_TECHNICIAN)->find($id);
            if (!$technician) {
                LoggingService::logError('Invalid technician access attempt', [
                    'requested_id' => $id,
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'route' => 'technicians.toggle-status',
                ]);
                
                return redirect()->route('technicians.index')
                    ->with('error', 'Technician not found. The requested technician does not exist or is not a valid technician.');
            }
        $technician->update(['is_active' => !$technician->is_active]);

        $status = $technician->is_active ? 'activated' : 'deactivated';
            
            LoggingService::logUserAction('toggled technician status', [
                'technician_id' => $id,
                'new_status' => $status,
            ], 'User', $id);

        return redirect()->back()->with('success', "Technician {$status} successfully.");
        } catch (\Exception $e) {
            LoggingService::logError('Failed to toggle technician status', [
                'technician_id' => $id,
            ], $e);
            throw new TechnicianException('Failed to toggle technician status: ' . $e->getMessage());
        }
    }

    /**
     * Export technicians to CSV
     */
    public function export(Request $request)
    {
        try {
            $query = User::where('role', AppConstants::ROLE_TECHNICIAN);

            // Apply search functionality using trait
            $searchFields = ['first_name', 'last_name', 'email'];
            $searchTerm = $this->getSearchTerm($request);
            $query = $this->applySearch($query, $searchFields, $searchTerm);

        // Apply filters
            $filterFields = [
                'status' => [
                    'type' => 'boolean',
                    'column' => 'is_active',
                    'operator' => '=',
                ],
            ];
            $this->applyFilters($query, $request, $filterFields);

        $technicians = $query->get();

            // Prepare data for export
        $headers = [
                'ID', 'First Name', 'Last Name', 'Email', 'Phone', 
                'Address', 'City', 'State', 'Zip Code', 'Status', 
                'Created At', 'Updated At'
            ];

            $data = $technicians->map(function ($technician) {
                return [
                    $technician->id,
                    $technician->first_name,
                    $technician->last_name,
                    $technician->email,
                    $technician->phone,
                    $technician->street_address,
                    $technician->city,
                    $technician->state,
                    $technician->zip_code,
                    $technician->is_active ? 'Active' : 'Inactive',
                    $technician->created_at,
                    $technician->updated_at,
                ];
            });

            LoggingService::logExport('technicians', $technicians->count());

            return $this->exportToCsv($data, $headers, 'technicians');
        } catch (\Exception $e) {
            LoggingService::logError('Failed to export technicians', [], $e);
            throw new TechnicianException('Failed to export technicians: ' . $e->getMessage());
        }
    }
}
