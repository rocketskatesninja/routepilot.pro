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
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
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
            $technician = User::where('role', AppConstants::ROLE_TECHNICIAN)->findOrFail($id);
        
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
            $technician = User::where('role', AppConstants::ROLE_TECHNICIAN)->findOrFail($id);
            
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
            $technician = User::where('role', AppConstants::ROLE_TECHNICIAN)->findOrFail($id);
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
            $technician = User::where('role', AppConstants::ROLE_TECHNICIAN)->findOrFail($id);
        
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
            $technician = User::where('role', AppConstants::ROLE_TECHNICIAN)->findOrFail($id);
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
