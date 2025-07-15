@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
        <div class="flex items-center space-x-4">
            <a href="{{ route('clients.index') }}" class="btn btn-ghost btn-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ $client->full_name }}</h1>
                <p class="text-gray-600 dark:text-gray-400">{{ $client->email }}</p>
            </div>
        </div>
        <div class="mt-4 lg:mt-0 flex space-x-2">
            <a href="{{ route('clients.edit', $client) }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit Client
            </a>
            <form action="{{ route('clients.toggle-status', $client) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="btn btn-{{ $client->is_active ? 'warning' : 'success' }}">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"></path>
                    </svg>
                    {{ $client->is_active ? 'Deactivate' : 'Activate' }}
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Client Profile -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div class="text-center mb-6">
                    <div class="avatar mb-4">
                        <div class="mask mask-squircle w-24 h-24">
                            @if($client->profile_photo)
                                <img src="{{ Storage::url($client->profile_photo) }}" alt="{{ $client->full_name }}">
                            @else
                                <div class="bg-primary text-primary-content rounded-lg flex items-center justify-center text-3xl font-bold">
                                    {{ substr($client->first_name, 0, 1) }}{{ substr($client->last_name, 0, 1) }}
                                </div>
                            @endif
                        </div>
                    </div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $client->full_name }}</h2>
                    <p class="text-gray-600 dark:text-gray-400">{{ $client->role }}</p>
                </div>

                <!-- Status Badges -->
                <div class="flex flex-wrap gap-2 mb-6">
                    @if($client->is_active)
                        <div class="badge badge-success">Active</div>
                    @else
                        <div class="badge badge-error">Inactive</div>
                    @endif
                    <div class="badge badge-{{ $client->status == 'active' ? 'success' : ($client->status == 'pending' ? 'warning' : 'error') }}">
                        {{ ucfirst($client->status) }}
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Contact Information</h3>
                    
                    <div class="space-y-3">
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <span class="text-gray-700 dark:text-gray-300">{{ $client->email }}</span>
                        </div>
                        
                        @if($client->phone)
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            <span class="text-gray-700 dark:text-gray-300">{{ $client->phone }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Address -->
                @if($client->street_address)
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Address</h3>
                    <div class="flex items-start space-x-3">
                        <svg class="w-5 h-5 text-gray-400 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <div class="text-gray-700 dark:text-gray-300">
                            <div>{{ $client->street_address }}</div>
                            @if($client->street_address_2)
                                <div>{{ $client->street_address_2 }}</div>
                            @endif
                            <div>{{ $client->city }}, {{ $client->state }} {{ $client->zip_code }}</div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Preferences -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Preferences</h3>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Appointment Reminders</span>
                            <div class="badge badge-{{ $client->appointment_reminders ? 'success' : 'error' }}">
                                {{ $client->appointment_reminders ? 'Yes' : 'No' }}
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Mailing List</span>
                            <div class="badge badge-{{ $client->mailing_list ? 'success' : 'error' }}">
                                {{ $client->mailing_list ? 'Yes' : 'No' }}
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Monthly Billing</span>
                            <div class="badge badge-{{ $client->monthly_billing ? 'success' : 'error' }}">
                                {{ $client->monthly_billing ? 'Yes' : 'No' }}
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Service Reports</span>
                            <div class="badge badge-info">{{ ucfirst(str_replace('_', ' ', $client->service_reports)) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <x-stat-card 
                    title="Locations" 
                    :value="$client->locations->count()" 
                    color="primary"
                    icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'></path>"
                />
                <x-stat-card 
                    title="Invoices" 
                    :value="$client->invoices->count()" 
                    color="success"
                    icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'></path>"
                />
                <x-stat-card 
                    title="Reports" 
                    :value="$client->reports->count()" 
                    color="warning"
                    icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'></path>"
                />
            </div>

            <!-- Tabs -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                <div class="tabs tabs-boxed p-4">
                    <a class="tab tab-active" onclick="showTab('locations')">Locations</a>
                    <a class="tab" onclick="showTab('invoices')">Invoices</a>
                    <a class="tab" onclick="showTab('reports')">Reports</a>
                    <a class="tab" onclick="showTab('activities')">Activities</a>
                </div>

                <div class="p-6">
                    <!-- Locations Tab -->
                    <div id="locations-tab" class="tab-content">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Locations</h3>
                            <a href="#" class="btn btn-primary btn-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Add Location
                            </a>
                        </div>
                        
                        @if($client->locations->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Location</th>
                                            <th>Address</th>
                                            <th>Pool Type</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($client->locations as $location)
                                        <tr>
                                            <td>
                                                <div class="font-medium">{{ $location->nickname ?: 'Main Location' }}</div>
                                            </td>
                                            <td>
                                                <div class="text-sm">{{ $location->city }}, {{ $location->state }}</div>
                                            </td>
                                            <td>
                                                <div class="badge badge-info">{{ ucfirst($location->pool_type ?? 'Unknown') }}</div>
                                            </td>
                                            <td>
                                                <div class="badge badge-{{ $location->status == 'active' ? 'success' : 'error' }}">
                                                    {{ ucfirst($location->status) }}
                                                </div>
                                            </td>
                                            <td>
                                                <a href="#" class="btn btn-ghost btn-xs">View</a>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-8">
                                <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <p class="text-gray-500">No locations found for this client.</p>
                                <a href="#" class="btn btn-primary mt-4">Add First Location</a>
                            </div>
                        @endif
                    </div>

                    <!-- Invoices Tab -->
                    <div id="invoices-tab" class="tab-content hidden">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Invoices</h3>
                            <a href="#" class="btn btn-primary btn-sm">Create Invoice</a>
                        </div>
                        
                        @if($client->invoices->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($client->invoices->take(5) as $invoice)
                                        <tr>
                                            <td>{{ $invoice->invoice_number }}</td>
                                            <td>{{ $invoice->service_date->format('M j, Y') }}</td>
                                            <td>${{ number_format($invoice->total_amount, 2) }}</td>
                                            <td>
                                                <div class="badge badge-{{ $invoice->status == 'paid' ? 'success' : ($invoice->status == 'overdue' ? 'error' : 'warning') }}">
                                                    {{ ucfirst($invoice->status) }}
                                                </div>
                                            </td>
                                            <td>
                                                <a href="#" class="btn btn-ghost btn-xs">View</a>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-8">
                                <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <p class="text-gray-500">No invoices found for this client.</p>
                                <a href="#" class="btn btn-primary mt-4">Create First Invoice</a>
                            </div>
                        @endif
                    </div>

                    <!-- Reports Tab -->
                    <div id="reports-tab" class="tab-content hidden">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Service Reports</h3>
                            <a href="#" class="btn btn-primary btn-sm">Create Report</a>
                        </div>
                        
                        @if($client->reports->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Location</th>
                                            <th>Technician</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($client->reports->take(5) as $report)
                                        <tr>
                                            <td>{{ $report->service_date->format('M j, Y') }}</td>
                                            <td>{{ $report->location->nickname ?: 'Main Location' }}</td>
                                            <td>{{ $report->technician->full_name }}</td>
                                            <td>
                                                <div class="badge badge-success">Completed</div>
                                            </td>
                                            <td>
                                                <a href="#" class="btn btn-ghost btn-xs">View</a>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-8">
                                <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <p class="text-gray-500">No service reports found for this client.</p>
                                <a href="#" class="btn btn-primary mt-4">Create First Report</a>
                            </div>
                        @endif
                    </div>

                    <!-- Activities Tab -->
                    <div id="activities-tab" class="tab-content hidden">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Activities</h3>
                        
                        @if($recentActivities->count() > 0)
                            <div class="space-y-4">
                                @foreach($recentActivities as $activity)
                                <div class="flex items-start space-x-3 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 bg-primary text-primary-content rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm text-gray-900 dark:text-white">{{ $activity->description }}</p>
                                        <p class="text-xs text-gray-500">{{ $activity->created_at->diffForHumans() }}</p>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8">
                                <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p class="text-gray-500">No recent activities found.</p>
                            </div>
                        @endif
                    </div>
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