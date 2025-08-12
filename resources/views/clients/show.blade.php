@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">{{ $client->full_name }}</h1>
            <p class="text-base-content/70 mt-2">{{ $client->email }}</p>
        </div>
        <div class="mt-4 lg:mt-0 flex space-x-2">
            <a href="{{ route('clients.edit', $client) }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit Client
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Client Profile -->
        <div class="lg:col-span-1">
            <div class="bg-base-100 shadow-xl rounded-lg p-6 border border-base-300">
                <!-- Client Photo -->
                <div class="mb-6">
                    @if($client->profile_photo)
                        <div class="w-full h-80 rounded-lg overflow-hidden">
                            <img src="{{ asset(Storage::url($client->profile_photo)) }}" alt="{{ $client->full_name }}" class="w-full h-full object-cover">
                        </div>
                    @else
                        <div class="w-full h-80 bg-base-200 rounded-lg flex items-center justify-center">
                            <div class="text-center">
                                <div class="bg-primary text-primary-content rounded-full w-32 h-32 flex items-center justify-center text-6xl font-bold mb-4">
                                    {{ substr($client->first_name, 0, 1) }}{{ substr($client->last_name, 0, 1) }}
                                </div>
                                <p class="text-base-content/50">No photo available</p>
                            </div>
                        </div>
                    @endif
                </div>



                <!-- Status Badge -->
                <div class="flex flex-wrap gap-2 mb-6">
                    <div class="badge badge-{{ $client->is_active ? 'success' : 'error' }}">
                        {{ $client->is_active ? 'Active' : 'Inactive' }}
                    </div>
                    <span class="text-base-content/70 ml-2">Created: {{ $client->created_at->format('M j, Y') }}</span>
                </div>

                <!-- Contact Information -->
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-semibold text-base-content">Contact Information</h3>
                    
                    <div class="space-y-3">
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-base-content/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                                                            <a href="mailto:{{ $client->email }}" class="text-blue-600 hover:text-blue-800 hover:underline">{{ $client->email }}</a>
                        </div>
                        
                        @if($client->phone)
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-base-content/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                                                            <a href="tel:{{ $client->phone }}" class="text-blue-600 hover:text-blue-800 hover:underline">{{ $client->phone }}</a>
                        </div>
                        @endif
                        @php
                            $address_street = trim($client->street_address . ($client->street_address_2 ? ' ' . $client->street_address_2 : ''));
                            $address_city = trim($client->city . ', ' . $client->state . ' ' . $client->zip_code);
                            $full_address = trim($address_street . ', ' . $address_city);
                            $user = auth()->user();
                            $mapsProvider = $user->maps_provider ?? 'google';
                            $mapsUrl = match($mapsProvider) {
                                'apple' => 'https://maps.apple.com/?q=' . urlencode($full_address),
                                'bing' => 'https://bing.com/maps/default.aspx?where1=' . urlencode($full_address),
                                default => 'https://maps.google.com/?q=' . urlencode($full_address),
                            };
                        @endphp
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-base-content/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            @if($user->role === 'admin' || $user->role === 'technician')
                                <a href="{{ $mapsUrl }}" target="_blank" class="text-blue-600 hover:text-blue-800 hover:underline">
                                    <div>{{ $address_street }}</div>
                                    <div class="text-base-content/70">{{ $address_city }}</div>
                                </a>
                            @else
                                <div>{{ $address_street }}</div>
                                <div class="text-base-content/70">{{ $address_city }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Preferences -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-base-content">Preferences</h3>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Appointment Reminders</span>
                            <div class="badge badge-{{ $client->appointment_reminders ? 'success' : 'error' }}">
                                {{ $client->appointment_reminders ? 'Yes' : 'No' }}
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Mailing List</span>
                            <div class="badge badge-{{ $client->mailing_list ? 'success' : 'error' }}">
                                {{ $client->mailing_list ? 'Yes' : 'No' }}
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Monthly Billing</span>
                            <div class="badge badge-{{ $client->monthly_billing ? 'success' : 'error' }}">
                                {{ $client->monthly_billing ? 'Yes' : 'No' }}
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Service Reports</span>
                            <div class="badge badge-info">{{ ucfirst(str_replace('_', ' ', $client->service_reports)) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Add extra space before Billing Information -->
                <div class="mt-8"></div>

                <!-- Billing Information -->
                <div class="space-y-4 mb-8">
                    <h3 class="text-lg font-semibold text-base-content">Billing Information</h3>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Current Balance</span>
                            <span class="font-semibold">${{ number_format($client->total_balance, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Last Payment</span>
                            <span>
                                @if($client->last_payment_date)
                                    {{ $client->last_payment_date->format('M j, Y') }}
                                @else
                                    <span class="text-base-content/50">Never</span>
                                @endif
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Last Invoice Total</span>
                            <span>
                                @if($client->last_invoice_total)
                                    ${{ number_format($client->last_invoice_total, 2) }}
                                @else
                                    <span class="text-base-content/50">N/A</span>
                                @endif
                            </span>
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
            <div class="bg-base-100 shadow-xl rounded-lg border border-base-300">
                <div class="tabs tabs-boxed p-4">
                    <a id="tab-locations" onclick="showTab('locations', event)" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out border-primary text-base-content focus:outline-none focus:border-primary-focus" style="margin-right: 1.5rem; cursor:pointer;">Locations</a>
                    <a id="tab-invoices" onclick="showTab('invoices', event)" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-base-content/70 hover:text-base-content hover:border-base-300 focus:outline-none focus:text-base-content focus:border-base-300 transition duration-150 ease-in-out" style="margin-right: 1.5rem; cursor:pointer;">Invoices</a>
                    <a id="tab-reports" onclick="showTab('reports', event)" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-base-content/70 hover:text-base-content hover:border-base-300 focus:outline-none focus:text-base-content focus:border-base-300 transition duration-150 ease-in-out" style="margin-right: 1.5rem; cursor:pointer;">Reports</a>
                    <a id="tab-activities" onclick="showTab('activities', event)" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-base-content/70 hover:text-base-content hover:border-base-300 focus:outline-none focus:text-base-content focus:border-base-300 transition duration-150 ease-in-out" style="cursor:pointer;">Activities</a>
                </div>

                <div class="p-6">
                    <!-- Locations Tab -->
                    <div id="locations-tab" class="tab-content" style="display: block !important;">
                        

                        

                        
                        @if($client->locations->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Location</th>
                                            <th>Address</th>
                                            <th>Pool Type</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($client->locations as $location)
                                        <tr>
                                            <td>
                                                <a href="{{ route('locations.show', $location) }}" class="text-blue-600 hover:text-blue-800 hover:underline font-medium">
                                                    {{ $location->nickname ?: 'Main Location' }}
                                                </a>
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
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-8">
                                <svg class="w-16 h-16 mx-auto mb-4 text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <p class="text-base-content/70">No locations found for this client.</p>
                                <a href="#" class="btn btn-primary mt-4">Add First Location</a>
                            </div>
                        @endif
                    </div>

                    <!-- Invoices Tab -->
                    <div id="invoices-tab" class="tab-content hidden">
                        

                        
                        @if($client->invoices->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($client->invoices->take(5) as $invoice)
                                        <tr>
                                            <td>
                                                <a href="{{ route('invoices.show', $invoice) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                                    {{ $invoice->invoice_number }}
                                                </a>
                                            </td>
                                            <td>{{ $invoice->service_date->format('M j, Y') }}</td>
                                            <td>${{ number_format($invoice->total_amount, 2) }}</td>
                                            <td>
                                                <div class="badge badge-{{ $invoice->status == 'paid' ? 'success' : ($invoice->status == 'overdue' ? 'error' : 'warning') }}">
                                                    {{ ucfirst($invoice->status) }}
                                                </div>
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
                                <p class="text-base-content/70">No invoices found for this client.</p>
                                <a href="#" class="btn btn-primary mt-4">Create First Invoice</a>
                            </div>
                        @endif
                    </div>

                    <!-- Reports Tab -->
                    <div id="reports-tab" class="tab-content hidden">
                        

                        
                        @if($client->reports->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Report #</th>
                                            <th>Date</th>
                                            <th>Location</th>
                                            <th>Technician</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($client->reports->take(5) as $report)
                                        <tr>
                                            <td>
                                                <a href="{{ route('reports.show', $report) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                                    {{ $report->id }}
                                                </a>
                                            </td>
                                            <td>{{ $report->service_date->format('M j, Y') }}</td>
                                            <td>
                                                <a href="{{ route('locations.show', $report->location) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                                    {{ $report->location->nickname ?: 'Main Location' }}
                                                </a>
                                            </td>
                                            <td>
                                                @if($report->technician)
                                                    <a href="{{ route('technicians.show', $report->technician) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                                        {{ $report->technician->full_name }}
                                                    </a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                <div class="badge badge-success">Completed</div>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-8">
                                <svg class="w-16 h-16 mx-auto mb-4 text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <p class="text-base-content/70">No service reports found for this client.</p>
                                <a href="#" class="btn btn-primary mt-4">Create First Report</a>
                            </div>
                        @endif
                    </div>

                    <!-- Activities Tab -->
                    <div id="activities-tab" class="tab-content hidden">
                        
                        @if($recentActivities->count() > 0)
                            <div class="space-y-4">
                                @foreach($recentActivities as $activity)
                                <div class="flex items-start space-x-3 p-4 bg-base-200 rounded-lg">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 bg-primary text-primary-content rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm text-base-content">{{ $activity->description }}</p>
                                        <p class="text-xs text-base-content/50">{{ $activity->created_at->diffForHumans() }}</p>
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
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
        content.style.display = 'none';
    });

    // Remove active classes and set inactive styles for all tab links
    document.querySelectorAll('.tabs a').forEach(link => {
        link.classList.remove('tab-active', 'border-primary', 'text-base-content', 'focus:border-primary-focus');
        link.classList.add('border-transparent', 'text-base-content/70');
    });

    // Show selected tab content
    const targetTab = document.getElementById(tabName + '-tab');
    if (targetTab) {
        targetTab.classList.remove('hidden');
        targetTab.style.display = 'block';
    }

    // Add active classes and styles to the clicked tab link
    const activeTab = document.getElementById('tab-' + tabName);
    if (activeTab) {
        activeTab.classList.add('tab-active', 'border-primary', 'text-base-content', 'focus:border-primary-focus');
        activeTab.classList.remove('border-transparent', 'text-base-content/70');
    }
}

// Initialize the first tab as active
// (default to locations tab)
document.addEventListener('DOMContentLoaded', function() {
    showTab('locations');
});
</script>
@endsection 