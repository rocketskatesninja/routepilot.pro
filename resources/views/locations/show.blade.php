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
            @if(auth()->user()->isAdmin() || auth()->user()->isTechnician())
            <a href="{{ route('reports.create', ['location_id' => $location->id]) }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                New Report
            </a>
            <a href="{{ route('invoices.create', ['location_id' => $location->id]) }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                </svg>
                New Invoice
            </a>
            @endif
            <a href="{{ route('locations.edit', $location) }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit Location
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Location Profile -->
        <div class="lg:col-span-1">
            <div class="bg-base-100 shadow-xl rounded-lg p-6 border border-base-300">
                <!-- Location Photos Slideshow -->
                <div class="mb-6">
                    @if($location->photos && count($location->photos) > 0)
                        <div class="carousel w-full h-80 rounded-lg overflow-hidden">
                            @foreach($location->photos as $index => $photo)
                                <div id="slide-{{ $index }}" class="carousel-item relative w-full">
                                    <img src="{{ asset(Storage::url($photo)) }}" alt="Location Photo {{ $index + 1 }}" class="w-full h-full object-cover">
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
                        @php
                            $address_street = trim($location->street_address . ($location->street_address_2 ? ' ' . $location->street_address_2 : ''));
                            $address_city = trim($location->city . ', ' . $location->state . ' ' . $location->zip_code);
                            $full_address = trim($address_street . ', ' . $address_city);
                            $user = auth()->user();
                        @endphp
                        @if($user->role === 'admin' || $user->role === 'technician')
                            <a href="{{ route('map.location', $location) }}" target="_blank" class="text-blue-600 hover:text-blue-800 hover:underline">
                                <div>{{ $address_street }}</div>
                                @if($location->street_address_2)
                                    <div>{{ $location->street_address_2 }}</div>
                                @endif
                                <div>{{ $address_city }}</div>
                            </a>
                        @else
                            <div>{{ $address_street }}</div>
                            @if($location->street_address_2)
                                <div>{{ $location->street_address_2 }}</div>
                            @endif
                            <div>{{ $address_city }}</div>
                        @endif
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
                                    <img src="{{ asset(Storage::url($location->assignedTechnician->profile_photo)) }}" alt="{{ $location->assignedTechnician->full_name }}">
                                @else
                                    <div class="bg-primary text-primary-content rounded-lg flex items-center justify-center">
                                        <span class="text-sm font-semibold">{{ substr($location->assignedTechnician->first_name, 0, 1) }}{{ substr($location->assignedTechnician->last_name, 0, 1) }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="font-medium text-base-content">
                                @if(auth()->user()->isAdmin())
                                    <a href="{{ route('technicians.show', $location->assignedTechnician) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                        {{ $location->assignedTechnician->full_name }}
                                    </a>
                                @else
                                    {{ $location->assignedTechnician->full_name }}
                                @endif
                            </div>
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
            <div class="bg-base-100 shadow-xl rounded-lg border border-base-300">
                <div class="tabs tabs-boxed p-4">
                    <a id="tab-invoices" onclick="showTab('invoices', event)" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out border-primary text-base-content focus:outline-none focus:border-primary-focus" style="margin-right: 1.5rem; cursor:pointer;">Invoices</a>
                    <a id="tab-reports" onclick="showTab('reports', event)" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-base-content/70 hover:text-base-content hover:border-base-300 focus:outline-none focus:text-base-content focus:border-base-300 transition duration-150 ease-in-out" style="cursor:pointer;">Reports</a>
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
                                @if(auth()->user()->isAdmin() || auth()->user()->isTechnician())
                                <a href="#" class="btn btn-primary mt-4">Create First Invoice</a>
                                @endif
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
                                @if(auth()->user()->isAdmin() || auth()->user()->isTechnician())
                                <a href="#" class="btn btn-primary mt-4">Create First Report</a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Interactive Map -->
    <div class="mt-8">
        <x-location-map :height="'400px'" :auto-populate-locations="false" />
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

// Function to show a specific location on the map
async function showLocationOnMap(locationId, locationName, locationAddress) {
    try {
        console.log('Showing location on map:', locationName, locationAddress);
        
        // Get the map instance from the map component
        const map = window.mapInstance;
        if (!map) {
            console.error('Map not initialized yet');
            alert('Map is still loading. Please wait a moment and try again.');
            return;
        }
        
        // Clear existing markers
        if (window.mapMarkers) {
            window.mapMarkers.forEach(marker => {
                map.removeLayer(marker);
            });
            window.mapMarkers = [];
        }
        
        // Geocode the address
        const coordinates = await geocodeAddress(locationAddress);
        if (coordinates) {
            // Create a marker for this location
            const marker = L.marker(coordinates, {
                icon: L.divIcon({
                    className: 'custom-div-icon',
                    html: `<div class="w-8 h-8 bg-primary rounded-full border-2 border-white shadow-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            </div>`,
                    iconSize: [32, 32],
                    iconAnchor: [16, 16]
                })
            });
            
            // Create popup content
            const popupContent = `
                <div class="p-3">
                    <div class="font-semibold text-primary text-lg">${locationName}</div>
                    <div class="text-sm text-gray-600 mt-1">${locationAddress}</div>
                    <div class="text-xs text-gray-500 mt-2">Location ID: ${locationId}</div>
                </div>
            `;
            
            marker.bindPopup(popupContent);
            marker.addTo(map);
            
            // Store marker reference
            if (!window.mapMarkers) window.mapMarkers = [];
            window.mapMarkers.push(marker);
            
            // Fit map to show the marker
            map.setView(coordinates, 15);
            
            // Open popup automatically
            marker.openPopup();
            
            console.log('Location marker added to map');
        } else {
            alert('Could not find coordinates for this address. Please check the address format.');
        }
    } catch (error) {
        console.error('Error showing location on map:', error);
        alert('Error showing location on map. Please try again.');
    }
}

// Geocoding function (same as in map component)
async function geocodeAddress(address) {
    try {
        console.log('Geocoding address:', address);
        
        const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&limit=1&countrycodes=us`);
        
        if (response.ok) {
            const data = await response.json();
            console.log('Geocoding response:', data);
            
            if (data && data.length > 0) {
                return [parseFloat(data[0].lat), parseFloat(data[0].lon)];
            }
        }
        
        return null;
    } catch (error) {
        console.warn('Geocoding failed:', error);
        return null;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    showTab('invoices');
});
</script>
@endsection 