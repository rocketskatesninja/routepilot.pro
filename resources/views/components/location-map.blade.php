@props(['locations' => [], 'technicians' => [], 'height' => '400px'])

<div class="bg-base-100 rounded-lg shadow-lg border border-base-300 overflow-hidden">
    <div class="p-4 border-b border-base-200">
        <h3 class="text-lg font-semibold text-base-content flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            Interactive Map
        </h3>
        <p class="text-sm text-base-content/70 mt-1">
            @if($locations->count() > 0 && $technicians->count() > 0)
                Showing {{ $locations->count() }} locations and {{ $technicians->count() }} technicians
            @elseif($locations->count() > 0)
                Showing {{ $locations->count() }} locations
            @elseif($technicians->count() > 0)
                Showing {{ $technicians->count() }} technicians
            @else
                No locations or technicians to display
            @endif
        </p>
    </div>
    
    <div id="map" style="height: {{ $height }}; width: 100%;" class="relative">
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
                <button id="reset-view" class="btn btn-sm btn-square btn-ghost" title="Reset View">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Legend -->
        <div class="absolute bottom-4 left-4 z-20">
            <div class="bg-base-100 rounded-lg shadow-lg p-3">
                <div class="text-sm font-medium text-base-content mb-2">Legend</div>
                <div class="space-y-2">
                    @if($locations->count() > 0)
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-primary rounded-full mr-2"></div>
                            <span class="text-xs text-base-content/70">Locations</span>
                        </div>
                    @endif
                    @if($technicians->count() > 0)
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-success rounded-full mr-2"></div>
                            <span class="text-xs text-base-content/70">Technicians</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Map data
    const mapData = {
        locations: @json($locations ? $locations->map(function($location) {
            return [
                'id' => $location->id,
                'name' => $location->nickname ?: ($location->client ? $location->client->full_name : 'Unknown Location'),
                'address' => $location->full_address ?? ($location->street_address . ', ' . $location->city . ', ' . $location->state . ' ' . $location->zip_code),
                'city' => $location->city,
                'state' => $location->state,
                'type' => 'location',
                'status' => $location->status,
                'technician' => $location->assignedTechnician ? $location->assignedTechnician->full_name : null
            ];
        }) : []),
        technicians: @json($technicians ? $technicians->map(function($technician) {
            return [
                'id' => $technician->id,
                'name' => $technician->full_name,
                'city' => $technician->city,
                'state' => $technician->state,
                'type' => 'technician',
                'status' => $technician->is_active ? 'active' : 'inactive'
            ];
        }) : [])
    };

    // Initialize map
    let map;
    let markers = [];
    
    async function initializeMap() {
        try {
            // Create map
            map = L.map('map-container').setView([39.8283, -98.5795], 4); // Center of USA
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(map);
            
            // Add markers for locations
            if (mapData.locations && mapData.locations.length > 0) {
                await addLocationMarkers();
            }
            
            // Add markers for technicians
            if (mapData.technicians && mapData.technicians.length > 0) {
                await addTechnicianMarkers();
            }
            
            // Fit map to show all markers
            if (markers && markers.length > 0) {
                const group = new L.featureGroup(markers);
                map.fitBounds(group.getBounds().pad(0.1));
            } else {
                // If no markers, show a message
                document.getElementById('map-loading').innerHTML = `
                    <div class="text-center text-warning">
                        <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-lg font-medium">No locations found</p>
                        <p class="text-sm">The map will show locations once addresses are added</p>
                    </div>
                `;
            }
            
            // Hide loading indicator
            document.getElementById('map-loading').style.display = 'none';
            
        } catch (error) {
            console.error('Error initializing map:', error);
            document.getElementById('map-loading').innerHTML = `
                <div class="text-center text-error">
                    <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                    <p class="text-lg font-medium">Map loading failed</p>
                    <p class="text-sm">Please refresh the page to try again</p>
                    <button onclick="location.reload()" class="btn btn-sm btn-error mt-2">Retry</button>
                </div>
            `;
        }
    }
    
    async function addLocationMarkers() {
        for (const location of mapData.locations) {
            try {
                const coordinates = await geocodeAddress(location.address);
                if (coordinates) {
                    const marker = L.marker(coordinates, {
                        icon: L.divIcon({
                            className: 'custom-div-icon',
                            html: `<div class="w-6 h-6 bg-primary rounded-full border-2 border-white shadow-lg flex items-center justify-center">
                                     <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                     </svg>
                                   </div>`,
                            iconSize: [24, 24],
                            iconAnchor: [12, 12]
                        })
                    });
                    
                    const popupContent = `
                        <div class="p-2">
                            <div class="font-semibold text-primary">${location.name}</div>
                            <div class="text-sm text-gray-600">${location.address}</div>
                            <div class="text-xs text-gray-500 mt-1">
                                Status: <span class="badge badge-${location.status === 'active' ? 'success' : 'error'} badge-xs">${location.status}</span>
                            </div>
                            ${location.technician ? `<div class="text-xs text-gray-500 mt-1">Technician: ${location.technician}</div>` : ''}
                        </div>
                    `;
                    
                    marker.bindPopup(popupContent);
                    marker.addTo(map);
                    markers.push(marker);
                }
            } catch (error) {
                console.warn(`Failed to geocode location: ${location.name}`, error);
            }
        }
    }
    
    async function addTechnicianMarkers() {
        for (const technician of mapData.technicians) {
            try {
                const address = `${technician.city}, ${technician.state}`;
                const coordinates = await geocodeAddress(address);
                if (coordinates) {
                    const marker = L.marker(coordinates, {
                        icon: L.divIcon({
                            className: 'custom-div-icon',
                            html: `<div class="w-6 h-6 bg-success rounded-full border-2 border-white shadow-lg flex items-center justify-center">
                                     <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                     </svg>
                                   </div>`,
                            iconSize: [24, 24],
                            iconAnchor: [12, 12]
                        })
                    });
                    
                    const popupContent = `
                        <div class="p-2">
                            <div class="font-semibold text-success">${technician.name}</div>
                            <div class="text-sm text-gray-600">${address}</div>
                            <div class="text-xs text-gray-500 mt-1">
                                Status: <span class="badge badge-${technician.status === 'active' ? 'success' : 'error'} badge-xs">${technician.status}</span>
                            </div>
                        </div>
                    `;
                    
                    marker.bindPopup(popupContent);
                    marker.addTo(map);
                    markers.push(marker);
                }
            } catch (error) {
                console.warn(`Failed to geocode technician: ${technician.name}`, error);
            }
        }
    }
    
    async function geocodeAddress(address) {
        try {
            // Use our backend MapService for better performance and caching
            const response = await fetch(`/api/geocode?address=${encodeURIComponent(address)}`);
            const data = await response.json();
            
            if (data.success && data.coordinates) {
                return [data.coordinates.lat, data.coordinates.lng];
            }
            return null;
        } catch (error) {
            console.warn('Geocoding failed:', error);
            return null;
        }
    }
    
    // Map controls
    document.getElementById('zoom-in').addEventListener('click', () => {
        map.zoomIn();
    });
    
    document.getElementById('zoom-out').addEventListener('click', () => {
        map.zoomOut();
    });
    
    document.getElementById('reset-view').addEventListener('click', () => {
        if (markers && markers.length > 0) {
            const group = new L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.1));
        } else {
            map.setView([39.8283, -98.5795], 4);
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
@endpush
