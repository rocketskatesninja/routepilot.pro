@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
        <div>
                            <h1 class="text-3xl font-bold text-base-content">{{ $location->nickname ?? 'Location' }}</h1>
            <p class="text-base-content/70 mt-2">{{ $location->client->full_name }}</p>
        </div>
        <div class="mt-4 lg:mt-0 flex space-x-2">
            <a href="{{ route('reports.create', ['location_id' => $location->id]) }}" class="btn btn-primary">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                New Report
            </a>
            <a href="{{ route('invoices.create', ['location_id' => $location->id]) }}" class="btn btn-secondary">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                </svg>
                New Invoice
            </a>
            <a href="{{ route('locations.edit', $location) }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit Location
            </a>
            <a href="{{ route('locations.index') }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Locations
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Location Profile -->
        <div class="lg:col-span-1">
            <div class="bg-base-100 shadow-xl rounded-lg p-6">
                <!-- Location Photos Slideshow -->
                <div class="mb-6">
                    @if($location->photos && count($location->photos) > 0)
                        <div class="carousel w-full h-80 rounded-lg overflow-hidden">
                            @foreach($location->photos as $index => $photo)
                                <div id="slide-{{ $index }}" class="carousel-item relative w-full">
                                    <img src="{{ Storage::url($photo) }}" alt="Location Photo {{ $index + 1 }}" class="w-full h-full object-cover">
                                    @if(count($location->photos) > 1)
                                        <div class="absolute flex justify-between transform -translate-y-1/2 left-5 right-5 top-1/2">
                                            <a href="#slide-{{ $index == 0 ? count($location->photos) - 1 : $index - 1 }}" class="btn btn-circle btn-sm bg-base-100/50 hover:bg-base-100/80">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                                </svg>
                                            </a> 
                                            <a href="#slide-{{ $index == count($location->photos) - 1 ? 0 : $index + 1 }}" class="btn btn-circle btn-sm bg-base-100/50 hover:bg-base-100/80">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                </svg>
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if(count($location->photos) > 1)
                            <div class="flex justify-center w-full py-2 gap-2">
                                @foreach($location->photos as $index => $photo)
                                    <a href="#slide-{{ $index }}" class="btn btn-xs">{{ $index + 1 }}</a>
                                @endforeach
                            </div>
                        @endif
                    @else
                        <!-- Fallback Icon when no photos -->
                        <div class="w-full h-80 bg-base-200 rounded-lg flex items-center justify-center">
                            <div class="text-center">
                                <svg class="w-24 h-24 mx-auto text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <p class="text-base-content/50 mt-2">No photos available</p>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Status Badges -->
                <div class="flex flex-wrap gap-2 mb-6">
                    @if($location->is_favorite)
                        <div class="badge badge-warning">Favorite</div>
                    @endif
                    <div class="badge badge-{{ $location->status == 'active' ? 'success' : 'error' }}">
                        {{ ucfirst($location->status) }}
                    </div>
                </div>

                <!-- Address Information -->
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-semibold text-base-content">Address</h3>
                    <div class="flex items-start space-x-3">
                        <svg class="w-5 h-5 text-base-content/50 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <div class="text-base-content">
                            <div>{{ $location->street_address }}</div>
                            @if($location->street_address_2)
                                <div>{{ $location->street_address_2 }}</div>
                            @endif
                            <div>{{ $location->city }}, {{ $location->state }} {{ $location->zip_code }}</div>
                        </div>
                    </div>
                </div>

                <!-- Pool Information -->
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-semibold text-base-content">Pool Details</h3>
                    <div class="space-y-2">
                        @if($location->pool_type)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Pool Type</span>
                            <div class="badge badge-info">{{ ucfirst($location->pool_type) }}</div>
                        </div>
                        @endif
                        @if($location->water_type)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Water Type</span>
                            <div class="badge badge-secondary">{{ ucfirst($location->water_type) }}</div>
                        </div>
                        @endif
                        @if($location->filter_type)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Filter Type</span>
                            <span class="text-base-content">{{ $location->filter_type }}</span>
                        </div>
                        @endif
                        @if($location->gallons)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Gallons</span>
                            <span class="text-base-content">{{ number_format($location->gallons) }}</span>
                        </div>
                        @endif
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Setting</span>
                            <div class="badge badge-outline">{{ ucfirst($location->setting) }}</div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Installation</span>
                            <div class="badge badge-outline">{{ ucfirst($location->installation) }}</div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Access Type</span>
                            <div class="badge badge-outline">{{ ucfirst($location->access) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Assigned Technician -->
                @if($location->assignedTechnician)
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-semibold text-base-content">Assigned Technician</h3>
                    <div class="flex items-center space-x-3">
                        <div class="avatar">
                            <div class="mask mask-squircle w-12 h-12">
                                @if($location->assignedTechnician->profile_photo)
                                    <img src="{{ Storage::url($location->assignedTechnician->profile_photo) }}" alt="{{ $location->assignedTechnician->full_name }}">
                                @else
                                    <div class="bg-primary text-primary-content rounded-lg flex items-center justify-center">
                                        <span class="text-sm font-semibold">{{ substr($location->assignedTechnician->first_name, 0, 1) }}{{ substr($location->assignedTechnician->last_name, 0, 1) }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="font-medium text-base-content">{{ $location->assignedTechnician->full_name }}</div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Service Information -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-base-content">Service Information</h3>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Service Frequency</span>
                            <div class="badge badge-outline">{{ ucfirst(str_replace('_', ' ', $location->service_frequency)) }}</div>
                        </div>
                        @if($location->service_day_1)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Primary Service Day</span>
                            <span class="text-base-content">{{ ucfirst($location->service_day_1) }}</span>
                        </div>
                        @endif
                        @if($location->service_day_2)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Secondary Service Day</span>
                            <span class="text-base-content">{{ ucfirst($location->service_day_2) }}</span>
                        </div>
                        @endif
                        @if($location->rate_per_visit)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Rate per Visit</span>
                            <span class="text-base-content">${{ number_format($location->rate_per_visit, 2) }}</span>
                        </div>
                        @endif
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Chemicals Included</span>
                            <div class="badge badge-{{ $location->chemicals_included ? 'success' : 'error' }}">
                                {{ $location->chemicals_included ? 'Yes' : 'No' }}
                            </div>
                        </div>
                        @if($location->assignedTechnician)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Assigned Technician</span>
                            <span class="text-base-content">{{ $location->assignedTechnician->full_name }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-stat-card 
                    title="Invoices" 
                    :value="$location->invoices->count()" 
                    color="success"
                    icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'></path>"
                />
                <x-stat-card 
                    title="Reports" 
                    :value="$location->reports->count()" 
                    color="warning"
                    icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'></path>"
                />
            </div>

            <!-- Tabs -->
            <div class="bg-base-100 shadow-xl rounded-lg">
                <div class="tabs tabs-boxed p-4">
                    <a class="tab tab-active" onclick="showTab('invoices', event)">Invoices</a>
                    <a class="tab" onclick="showTab('reports', event)">Reports</a>
                </div>

                <div class="p-6">
                    <!-- Invoices Tab -->
                    <div id="invoices-tab" class="tab-content" style="display: block !important;">
                        @if($location->invoices->count() > 0)
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
                                        @foreach($location->invoices->take(10) as $invoice)
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
                                <svg class="w-16 h-16 mx-auto mb-4 text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <p class="text-base-content/70">No invoices found for this location.</p>
                                <a href="#" class="btn btn-primary mt-4">Create First Invoice</a>
                            </div>
                        @endif
                    </div>

                    <!-- Reports Tab -->
                    <div id="reports-tab" class="tab-content hidden">
                        @if($location->reports->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Technician</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($location->reports->take(10) as $report)
                                        <tr>
                                            <td>{{ $report->service_date->format('M j, Y') }}</td>
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
                                <svg class="w-16 h-16 mx-auto mb-4 text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <p class="text-base-content/70">No service reports found for this location.</p>
                                <a href="#" class="btn btn-primary mt-4">Create First Report</a>
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
    showTab('invoices');
});
</script>
@endsection 