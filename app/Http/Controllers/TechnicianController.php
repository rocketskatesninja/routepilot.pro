<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TechnicianController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::where('role', 'technician');

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
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Sort functionality
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $technicians = $query->paginate(15);

        return view('technicians.index', compact('technicians'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('technicians.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'street_address' => 'nullable|string|max:255',
            'street_address_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:10',
            'notes_by_admin' => 'nullable|string',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'password' => 'required|string|min:8|confirmed',
            'is_active' => 'boolean',
        ]);

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->store('profile-photos', 'public');
            $validated['profile_photo'] = $path;
        }

        // Set default values
        $validated['role'] = 'technician';
        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = $request->has('is_active');

        User::create($validated);

        return redirect()->route('technicians.index')
            ->with('success', 'Technician created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $technician = User::where('role', 'technician')->findOrFail($id);
        
        // Get assigned locations
        $assignedLocations = $technician->assignedLocations()->paginate(10);
        
        // Get recent reports
        $recentReports = $technician->reports()->latest()->take(5)->get();
        
        // Get recent invoices
        $recentInvoices = $technician->invoices()->latest()->take(5)->get();
        
        // Get recent activities
        $recentActivities = $technician->activities()->latest()->take(10)->get();

        return view('technicians.show', compact(
            'technician', 
            'assignedLocations', 
            'recentReports', 
            'recentInvoices', 
            'recentActivities'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $technician = User::where('role', 'technician')->findOrFail($id);
        return view('technicians.edit', compact('technician'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $technician = User::where('role', 'technician')->findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($technician->id)],
            'phone' => 'nullable|string|max:20',
            'street_address' => 'nullable|string|max:255',
            'street_address_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:10',
            'notes_by_admin' => 'nullable|string',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'password' => 'nullable|string|min:8|confirmed',
            'is_active' => 'boolean',
        ]);

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            // Delete old photo if exists
            if ($technician->profile_photo) {
                Storage::disk('public')->delete($technician->profile_photo);
            }
            $path = $request->file('profile_photo')->store('profile-photos', 'public');
            $validated['profile_photo'] = $path;
        }

        // Update password only if provided
        if ($request->filled('password')) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $validated['is_active'] = $request->has('is_active');

        $technician->update($validated);

        return redirect()->route('technicians.show', $technician)
            ->with('success', 'Technician updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $technician = User::where('role', 'technician')->findOrFail($id);
        
        // Delete profile photo if exists
        if ($technician->profile_photo) {
            Storage::disk('public')->delete($technician->profile_photo);
        }
        
        $technician->delete();

        return redirect()->route('technicians.index')
            ->with('success', 'Technician deleted successfully.');
    }

    /**
     * Toggle technician status
     */
    public function toggleStatus(string $id)
    {
        $technician = User::where('role', 'technician')->findOrFail($id);
        $technician->update(['is_active' => !$technician->is_active]);

        $status = $technician->is_active ? 'activated' : 'deactivated';
        return redirect()->back()->with('success', "Technician {$status} successfully.");
    }

    /**
     * Export technicians to CSV
     */
    public function export(Request $request)
    {
        $query = User::where('role', 'technician');

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $technicians = $query->get();

        $filename = 'technicians_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function() use ($technicians) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'ID', 'First Name', 'Last Name', 'Email', 'Phone', 
                'Address', 'City', 'State', 'Zip Code', 'Status', 
                'Created At', 'Updated At'
            ]);

            // CSV data
            foreach ($technicians as $technician) {
                fputcsv($file, [
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
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
