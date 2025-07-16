@extends('layouts.app')

@section('title', $technician->full_name)

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">{{ $technician->full_name }}</h1>
            <p class="text-base-content/70 mt-2">{{ $technician->email }}</p>
        </div>
        <div class="mt-4 lg:mt-0 flex space-x-2">
            <a href="{{ route('technicians.edit', $technician) }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit Technician
            </a>
            <a href="{{ route('technicians.index') }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Technicians
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Technician Profile -->
        <div class="lg:col-span-1">
            <div class="bg-base-100 shadow-xl rounded-lg p-6">
                <!-- Technician Photo -->
                <div class="mb-6">
                    @if($technician->profile_photo)
                        <div class="w-full h-80 rounded-lg overflow-hidden">
                            <img src="{{ Storage::url($technician->profile_photo) }}" alt="{{ $technician->full_name }}" class="w-full h-full object-cover">
                        </div>
                    @else
                        <div class="w-full h-80 bg-base-200 rounded-lg flex items-center justify-center">
                            <div class="text-center">
                                <div class="bg-primary text-primary-content rounded-full w-32 h-32 flex items-center justify-center text-6xl font-bold mb-4">
                                    {{ strtoupper(substr($technician->first_name, 0, 1) . substr($technician->last_name, 0, 1)) }}
                                </div>
                                <p class="text-base-content/50">No photo available</p>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Contact Information -->
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-semibold text-base-content">Contact Information</h3>
                    
                    <div class="space-y-3">
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-base-content/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <a href="mailto:{{ $technician->email }}" class="text-base-content hover:text-primary hover:underline">{{ $technician->email }}</a>
                        </div>
                        
                        @if($technician->phone)
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-base-content/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            <a href="tel:{{ $technician->phone }}" class="text-base-content hover:text-primary hover:underline">{{ $technician->phone }}</a>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Address -->
                @if($technician->street_address)
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-semibold text-base-content">Address</h3>
                    <div class="flex items-start space-x-3">
                        <svg class="w-5 h-5 text-base-content/50 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <div class="text-base-content">
                            <div>{{ $technician->street_address }}</div>
                            @if($technician->street_address_2)
                                <div>{{ $technician->street_address_2 }}</div>
                            @endif
                            <div>{{ $technician->city }}, {{ $technician->state }} {{ $technician->zip_code }}</div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Account Information -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-base-content">Account Information</h3>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Status</span>
                            <div class="badge badge-{{ $technician->is_active ? 'success' : 'error' }}">
                                {{ $technician->is_active ? 'Active' : 'Inactive' }}
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Role</span>
                            <div class="badge badge-info">Technician</div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Created</span>
                            <span class="text-base-content">{{ $technician->created_at->format('M d, Y') }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Last Updated</span>
                            <span class="text-base-content">{{ $technician->updated_at->format('M d, Y') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Admin Notes -->
                @if($technician->notes_by_admin)
                <div class="space-y-4 mt-6">
                    <h3 class="text-lg font-semibold text-base-content">Admin Notes</h3>
                    <div class="bg-base-200 rounded-lg p-4">
                        <p class="text-base-content">{{ $technician->notes_by_admin }}</p>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <x-stat-card 
                    title="Assigned Locations" 
                    :value="$assignedLocations->total()" 
                    color="primary"
                    icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z'></path><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M15 11a3 3 0 11-6 0 3 3 0 016 0z'></path>"
                />
                <x-stat-card 
                    title="Reports Created" 
                    :value="$recentReports->count()" 
                    color="success"
                    icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'></path>"
                />
                <x-stat-card 
                    title="Invoices Created" 
                    :value="$recentInvoices->count()" 
                    color="warning"
                    icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'></path>"
                />
            </div>

            <!-- Tabs -->
            <div class="bg-base-100 shadow-xl rounded-lg">
                <div class="tabs tabs-boxed p-4">
                    <a class="tab tab-active" onclick="showTab('locations', event)">Assigned Locations</a>
                    <a class="tab" onclick="showTab('reports', event)">Recent Reports</a>
                    <a class="tab" onclick="showTab('invoices', event)">Recent Invoices</a>
                    <a class="tab" onclick="showTab('activities', event)">Recent Activities</a>
                </div>

                <div class="p-6">
                    <!-- Assigned Locations Tab -->
                    <div id="locations-tab" class="tab-content" style="display: block !important;">
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
                                                    <div class="font-medium">{{ $location->name }}</div>
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
                                                    <a href="{{ route('locations.show', $location) }}" class="btn btn-ghost btn-xs">
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
                                <svg class="w-16 h-16 mx-auto mb-4 text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <p class="text-base-content/70">No assigned locations found.</p>
                            </div>
                        @endif
                    </div>

                    <!-- Recent Reports Tab -->
                    <div id="reports-tab" class="tab-content hidden">
                        @if($recentReports->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Location</th>
                                            <th>Client</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentReports as $report)
                                            <tr>
                                                <td>{{ $report->service_date->format('M j, Y') }}</td>
                                                <td>{{ $report->location->name ?? 'N/A' }}</td>
                                                <td>{{ $report->client->full_name ?? 'N/A' }}</td>
                                                <td>
                                                    <span class="badge badge-success">Completed</span>
                                                </td>
                                                <td>
                                                    <a href="{{ route('reports.show', $report) }}" class="btn btn-ghost btn-xs">
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-8">
                                <svg class="w-16 h-16 mx-auto mb-4 text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <p class="text-base-content/70">No recent reports found.</p>
                            </div>
                        @endif
                    </div>

                    <!-- Recent Invoices Tab -->
                    <div id="invoices-tab" class="tab-content hidden">
                        @if($recentInvoices->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Date</th>
                                            <th>Client</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentInvoices as $invoice)
                                            <tr>
                                                <td>{{ $invoice->invoice_number }}</td>
                                                <td>{{ $invoice->service_date->format('M j, Y') }}</td>
                                                <td>{{ $invoice->client->full_name ?? 'N/A' }}</td>
                                                <td>${{ number_format($invoice->total_amount, 2) }}</td>
                                                <td>
                                                    <span class="badge badge-{{ $invoice->status == 'paid' ? 'success' : ($invoice->status == 'overdue' ? 'error' : 'warning') }}">
                                                        {{ ucfirst($invoice->status) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-ghost btn-xs">
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-8">
                                <svg class="w-16 h-16 mx-auto mb-4 text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                                <p class="text-base-content/70">No recent invoices found.</p>
                            </div>
                        @endif
                    </div>

                    <!-- Recent Activities Tab -->
                    <div id="activities-tab" class="tab-content hidden">
                        @if($recentActivities->count() > 0)
                            <div class="space-y-4">
                                @foreach($recentActivities as $activity)
                                    <div class="flex items-start space-x-3 p-4 bg-base-200 rounded-lg">
                                        <div class="flex-shrink-0">
                                            <div class="w-8 h-8 bg-primary text-primary-content rounded-full flex items-center justify-center">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-base-content">{{ $activity->description }}</p>
                                            <p class="text-xs text-base-content/70">{{ $activity->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8">
                                <svg class="w-16 h-16 mx-auto mb-4 text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p class="text-base-content/70">No recent activities found.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabName, event = null) {
    console.log('Switching to tab:', tabName);
    
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
        content.style.display = 'none';
        console.log('Hiding tab content:', content.id);
    });
    
    // Remove active class from all tabs
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('tab-active');
    });
    
    // Show selected tab content
    const targetTab = document.getElementById(tabName + '-tab');
    if (targetTab) {
        targetTab.classList.remove('hidden');
        targetTab.style.display = 'block';
        console.log('Showing tab content:', targetTab.id);
    } else {
        console.error('Tab content not found:', tabName + '-tab');
    }
    
    // Add active class to clicked tab (only if event is provided)
    if (event && event.target) {
        event.target.classList.add('tab-active');
    }
}

// Initialize the first tab as active
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing tabs');
    showTab('locations');
});
</script>
@endsection 