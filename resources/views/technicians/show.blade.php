@extends('layouts.app')

@section('title', $technician->full_name)

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex justify-between items-start mb-6">
        <div class="flex items-center space-x-4">
            <div class="avatar">
                <div class="mask mask-squircle w-20 h-20">
                    @if($technician->profile_photo)
                        <img src="{{ Storage::url($technician->profile_photo) }}" 
                             alt="{{ $technician->full_name }}">
                    @else
                        <div class="bg-primary text-primary-content rounded-full w-20 h-20 flex items-center justify-center">
                            <span class="text-2xl font-bold">
                                {{ strtoupper(substr($technician->first_name, 0, 1) . substr($technician->last_name, 0, 1)) }}
                            </span>
                        </div>
                    @endif
                </div>
            </div>
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ $technician->full_name }}</h1>
                <p class="text-gray-600 dark:text-gray-400">Technician ID: {{ $technician->id }}</p>
                <div class="flex items-center space-x-2 mt-2">
                    @if($technician->is_active)
                        <span class="badge badge-success">Active</span>
                    @else
                        <span class="badge badge-warning">Inactive</span>
                    @endif
                    <span class="badge badge-info">Technician</span>
                </div>
            </div>
        </div>
        <div class="flex space-x-2">
            <a href="{{ route('technicians.edit', $technician) }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit
            </a>
            <a href="{{ route('technicians.toggle-status', $technician) }}" 
               onclick="return confirm('Are you sure you want to {{ $technician->is_active ? 'deactivate' : 'activate' }} this technician?')"
               class="btn {{ $technician->is_active ? 'btn-warning' : 'btn-success' }}">
                @if($technician->is_active)
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"></path>
                    </svg>
                    Deactivate
                @else
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Activate
                @endif
            </a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <x-stat-card 
            title="Assigned Locations" 
            :value="$assignedLocations->total()" 
            color="primary"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z'></path><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M15 11a3 3 0 11-6 0 3 3 0 016 0z'></path>"
        />
        <x-stat-card 
            title="Reports Created" 
            :value="$recentReports->count()" 
            color="secondary"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'></path>"
        />
        <x-stat-card 
            title="Invoices Created" 
            :value="$recentInvoices->count()" 
            color="accent"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'></path>"
        />
        <x-stat-card 
            title="Member Since" 
            :value="$technician->created_at->format('M Y')" 
            color="info"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'></path>"
        />
    </div>

    <!-- Contact Information -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title">Contact Information</h2>
                <div class="space-y-3">
                    <div class="flex items-center space-x-3">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <span>{{ $technician->email }}</span>
                    </div>
                    @if($technician->phone)
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            <span>{{ $technician->phone }}</span>
                        </div>
                    @endif
                    @if($technician->street_address)
                        <div class="flex items-start space-x-3">
                            <svg class="w-5 h-5 text-gray-500 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <div>
                                <div>{{ $technician->street_address }}</div>
                                @if($technician->street_address_2)
                                    <div>{{ $technician->street_address_2 }}</div>
                                @endif
                                <div>{{ $technician->city }}, {{ $technician->state }} {{ $technician->zip_code }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title">Account Information</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Status:</span>
                        <span class="badge {{ $technician->is_active ? 'badge-success' : 'badge-warning' }}">
                            {{ $technician->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Role:</span>
                        <span class="badge badge-info">Technician</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Created:</span>
                        <span>{{ $technician->created_at->format('M d, Y g:i A') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Last Updated:</span>
                        <span>{{ $technician->updated_at->format('M d, Y g:i A') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notes -->
    @if($technician->notes_by_admin)
        <div class="card bg-base-100 shadow-xl mb-6">
            <div class="card-body">
                <h2 class="card-title">Admin Notes</h2>
                <p class="text-gray-700 dark:text-gray-300">{{ $technician->notes_by_admin }}</p>
            </div>
        </div>
    @endif

    <!-- Tabs -->
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="tabs tabs-boxed">
                <a class="tab tab-active" onclick="showTab('locations')">Assigned Locations</a>
                <a class="tab" onclick="showTab('reports')">Recent Reports</a>
                <a class="tab" onclick="showTab('invoices')">Recent Invoices</a>
                <a class="tab" onclick="showTab('activities')">Recent Activities</a>
            </div>

            <!-- Assigned Locations Tab -->
            <div id="locations-tab" class="tab-content">
                <div class="mt-6">
                    @if($assignedLocations->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="table table-zebra w-full">
                                <thead>
                                    <tr>
                                        <th>Location</th>
                                        <th>Client</th>
                                        <th>Address</th>
                                        <th>Pool Type</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($assignedLocations as $location)
                                        <tr>
                                            <td>
                                                <div class="font-bold">{{ $location->name }}</div>
                                                <div class="text-sm opacity-50">ID: {{ $location->id }}</div>
                                            </td>
                                            <td>{{ $location->client->full_name ?? 'N/A' }}</td>
                                            <td>
                                                <div class="text-sm">
                                                    <div>{{ $location->street_address }}</div>
                                                    <div class="text-gray-500">{{ $location->city }}, {{ $location->state }}</div>
                                                </div>
                                            </td>
                                            <td>{{ $location->pool_type ?? 'N/A' }}</td>
                                            <td>
                                                @if($location->is_active)
                                                    <span class="badge badge-success">Active</span>
                                                @else
                                                    <span class="badge badge-warning">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('locations.show', $location) }}" class="btn btn-ghost btn-sm">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $assignedLocations->links() }}
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No assigned locations</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                This technician hasn't been assigned to any locations yet.
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Reports Tab -->
            <div id="reports-tab" class="tab-content hidden">
                <div class="mt-6">
                    @if($recentReports->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentReports as $report)
                                <div class="card bg-base-200">
                                    <div class="card-body">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h3 class="font-semibold">Report #{{ $report->id }}</h3>
                                                <p class="text-sm text-gray-600">{{ $report->created_at->format('M d, Y g:i A') }}</p>
                                            </div>
                                            <span class="badge badge-info">Service Report</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No reports found</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                This technician hasn't created any reports yet.
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Invoices Tab -->
            <div id="invoices-tab" class="tab-content hidden">
                <div class="mt-6">
                    @if($recentInvoices->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentInvoices as $invoice)
                                <div class="card bg-base-200">
                                    <div class="card-body">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h3 class="font-semibold">Invoice #{{ $invoice->id }}</h3>
                                                <p class="text-sm text-gray-600">{{ $invoice->created_at->format('M d, Y g:i A') }}</p>
                                            </div>
                                            <span class="badge badge-accent">Invoice</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No invoices found</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                This technician hasn't created any invoices yet.
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Activities Tab -->
            <div id="activities-tab" class="tab-content hidden">
                <div class="mt-6">
                    @if($recentActivities->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentActivities as $activity)
                                <div class="card bg-base-200">
                                    <div class="card-body">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h3 class="font-semibold">{{ $activity->description }}</h3>
                                                <p class="text-sm text-gray-600">{{ $activity->created_at->format('M d, Y g:i A') }}</p>
                                            </div>
                                            <span class="badge badge-neutral">Activity</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No activities found</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                This technician hasn't performed any activities yet.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Remove active class from all tabs
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('tab-active');
    });
    
    // Show selected tab content
    document.getElementById(tabName + '-tab').classList.remove('hidden');
    
    // Add active class to clicked tab
    event.target.classList.add('tab-active');
}
</script>
@endsection 