@extends('layouts.app')

@section('title', 'Map - ' . ($location->nickname ?: $location->street_address))

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Location Map</h1>
            <p class="text-base-content/70 mt-2">{{ $location->nickname ?: ($location->client ? $location->client->full_name : 'Unknown Location') }}</p>
        </div>
        <div class="flex space-x-2">
            <a href="{{ route('locations.show', $location) }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Location
            </a>
        </div>
    </div>

    <!-- Location Details Card -->
    <div class="bg-base-100 rounded-lg shadow-lg border border-base-300 p-6 mb-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Location Info -->
            <div>
                <h3 class="text-lg font-semibold text-base-content mb-4">Location Details</h3>
                <div class="space-y-2">
                    <div>
                        <span class="text-base-content/70">Client:</span>
                        <span class="font-medium">{{ $location->client ? $location->client->full_name : 'N/A' }}</span>
                    </div>
                    @if($location->nickname)
                    <div>
                        <span class="text-base-content/70">Nickname:</span>
                        <span class="font-medium">{{ $location->nickname }}</span>
                    </div>
                    @endif
                    <div>
                        <span class="text-base-content/70">Address:</span>
                        <span class="font-medium">
                            {{ $location->street_address }}
                            @if($location->street_address_2), {{ $location->street_address_2 }}@endif
                            <br>{{ $location->city }}, {{ $location->state }} {{ $location->zip_code }}
                        </span>
                    </div>
                    @if($location->pool_type)
                    <div>
                        <span class="text-base-content/70">Pool Type:</span>
                        <span class="badge badge-info">{{ ucfirst($location->pool_type) }}</span>
                    </div>
                    @endif
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div>
                <h3 class="text-lg font-semibold text-base-content mb-4">Quick Actions</h3>
                <div class="space-y-2">
                    <a href="{{ route('locations.edit', $location) }}" class="btn btn-sm btn-outline w-full">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Edit Location
                    </a>
                    @if($location->client)
                    <a href="{{ route('clients.show', $location->client) }}" class="btn btn-sm btn-outline w-full">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        View Client
                    </a>
                    @endif
                    <a href="{{ route('reports.create', ['location_id' => $location->id]) }}" class="btn btn-sm btn-primary w-full">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Create Report
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Interactive Map -->
    <div class="bg-base-100 rounded-lg shadow-lg border border-base-300 overflow-hidden">
        <div class="p-4 border-b border-base-200">
            <h3 class="text-lg font-semibold text-base-content flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Location Map
            </h3>
            <p class="text-sm text-base-content/70 mt-1">
                Showing location on interactive map
            </p>
        </div>
        
        <div id="map" style="height: 600px; width: 100%;" class="relative">
            <!-- Loading indicator -->
            <div id="map-loading" class="absolute inset-0 bg-base-200 flex items-center justify-center z-10">
                <div class="text-center">
                    <div class="loading loading-spinner loading-lg text-primary"></div>
                    <p class="mt-2 text-base-content/70">Loading map...</p>
                </div>
            </div>
            
            <!-- Map container -->
            <div id="map-container" class="absolute inset-0"></div>
            
            <!-- Map controls -->
            <div class="absolute top-4 right-4 z-20">
                <div class="bg-base-100 rounded-lg shadow-lg p-2 space-y-2">
                    <button id="zoom-in" class="btn btn-sm btn-square btn-ghost" title="Zoom In">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path>
                        </svg>
                    </button>
                    <button id="zoom-out" class="btn btn-sm btn-square btn-ghost" title="Zoom Out">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 13l-3-3m0 0l3-3m-3 3h6"></path>
                        </svg>
                    </button>
                    <button id="center-location" class="btn btn-sm btn-square btn-primary" title="Center on Location">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@section('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<script>
console.log('Dedicated location map loading...');

document.addEventListener('DOMContentLoaded', function() {
    // Location data
    const locationData = {
        id: {{ $location->id }},
        name: '{{ $location->nickname ?: ($location->client ? $location->client->full_name : 'Unknown Location') }}',
        address: '{{ $location->full_address ?? ($location->street_address . ', ' . $location->city . ', ' . $location->state . ' ' . $location->zip_code) }}',
        client: '{{ $location->client ? $location->client->full_name : '' }}'
    };

    console.log('Location data:', locationData);

    let map;
    let locationMarker;

    async function initializeMap() {
        try {
            console.log('Initializing dedicated location map...');
            
            // Create map
            map = L.map('map-container').setView([39.8283, -98.5795], 4); // Center of USA initially
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(map);
            
            // Hide loading indicator
            document.getElementById('map-loading').style.display = 'none';
            
            // Show the location on the map
            await showLocationOnMap();
            
            console.log('Dedicated location map initialized successfully');
            
        } catch (error) {
            console.error('Error initializing map:', error);
            document.getElementById('map-loading').innerHTML = '<div class="text-center"><p class="text-error">Error loading map</p></div>';
        }
    }

    async function showLocationOnMap() {
        try {
            console.log('Showing location on map:', locationData.address);
            
            // Geocode the address
            const coordinates = await geocodeAddress(locationData.address);
            if (coordinates) {
                // Create a marker for this location
                locationMarker = L.marker(coordinates, {
                    icon: L.divIcon({
                        className: 'custom-div-icon',
                        html: `<div class="w-10 h-10 bg-primary rounded-full border-3 border-white shadow-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                </div>`,
                        iconSize: [40, 40],
                        iconAnchor: [20, 20]
                    })
                });
                
                // Create popup content
                const popupContent = `
                    <div class="p-3">
                        <div class="font-semibold text-primary text-lg">${locationData.name}</div>
                        ${locationData.client ? `<div class="text-sm text-gray-600 mt-1">Client: ${locationData.client}</div>` : ''}
                        <div class="text-sm text-gray-600 mt-1">${locationData.address}</div>
                        <div class="text-xs text-gray-500 mt-2">Location ID: ${locationData.id}</div>
                    </div>
                `;
                
                locationMarker.bindPopup(popupContent);
                locationMarker.addTo(map);
                
                // Fit map to show the marker
                map.setView(coordinates, 16);
                
                // Open popup automatically
                locationMarker.openPopup();
                
                console.log('Location marker added to map');
            } else {
                throw new Error('Could not geocode the address');
            }
        } catch (error) {
            console.error('Error showing location:', error);
            
            // Show error message on map
            const errorPopup = L.popup()
                .setLatLng([39.8283, -98.5795])
                .setContent(`<div class="p-2 text-error">Could not find location: ${locationData.address}</div>`)
                .openOn(map);
        }
    }

    // Geocoding function
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

    // Map controls
    document.getElementById('zoom-in').addEventListener('click', () => {
        if (map) map.zoomIn();
    });
    
    document.getElementById('zoom-out').addEventListener('click', () => {
        if (map) map.zoomOut();
    });
    
    document.getElementById('center-location').addEventListener('click', () => {
        if (map && locationMarker) {
            map.setView(locationMarker.getLatLng(), 16);
            locationMarker.openPopup();
        }
    });

    // Initialize the map
    initializeMap();
});
</script>

<style>
.custom-div-icon {
    background: transparent;
    border: none;
}

.leaflet-popup-content-wrapper {
    border-radius: 8px;
}

.leaflet-popup-tip {
    background: white;
}
</style>
@endsection
@endsection
