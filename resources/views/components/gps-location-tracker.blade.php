@if(auth()->check() && auth()->user()->isTechnician())
<div class="bg-base-100 rounded-lg shadow-lg border border-base-300 p-4" x-data="gpsLocationTracker({{ $updateInterval }}, {{ $showStatus ? 'true' : 'false' }}, {{ $initialSharingEnabled ?? 'false' }})">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-base-content flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            GPS Location Tracker
        </h3>
        
        <div class="flex items-center space-x-2">
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" x-model="locationSharingEnabled" @change="toggleLocationSharing" class="sr-only peer">
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                <span class="ml-3 text-sm font-medium text-base-content">Share Location</span>
            </label>
        </div>
    </div>

    <div class="space-y-4">
        <!-- Status Display -->
        <div x-show="showStatus" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-base-200 rounded-lg p-3">
                <div class="text-sm text-base-content/70">Status</div>
                <div class="text-lg font-semibold" :class="{
                    'text-success': locationStatus === 'active',
                    'text-warning': locationStatus === 'requesting',
                    'text-error': locationStatus === 'denied',
                    'text-base-content/50': locationStatus === 'inactive'
                }">
                    <span x-text="getStatusText()"></span>
                    <div x-show="locationStatus === 'active' && locationSharingEnabled && updateTimer" class="text-xs text-success/70 mt-1">
                        <div class="flex items-center">
                            <div class="w-2 h-2 bg-success rounded-full animate-pulse mr-2"></div>
                            Auto-updating every <span x-text="Math.round(updateInterval/1000)"></span>s
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-base-200 rounded-lg p-3">
                <div class="text-sm text-base-content/70">Last Update</div>
                <div class="text-lg font-semibold" x-text="lastUpdateTime || 'Never'"></div>
            </div>
            
            <div class="bg-base-200 rounded-lg p-3">
                <div class="text-sm text-base-content/70">Coordinates</div>
                <div class="text-sm font-mono" x-text="currentCoordinates || 'Not available'"></div>
            </div>
        </div>

        <!-- Location Request Button -->
        <div x-show="locationStatus === 'inactive'" class="text-center">
            <button @click="requestLocation" class="btn btn-primary">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Enable Location Sharing
            </button>
        </div>

        <!-- Manual Update Button -->
        <div x-show="locationStatus === 'active'" class="text-center">
            <button @click="updateLocationNow" class="btn btn-secondary" :disabled="isUpdating">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <span x-text="isUpdating ? 'Updating...' : 'Update Location Now'"></span>
            </button>
        </div>

        <!-- Error Messages -->
        <div x-show="errorMessage" class="alert alert-error">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
            <span x-text="errorMessage"></span>
        </div>

        <!-- Success Messages -->
        <div x-show="successMessage" class="alert alert-success">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span x-text="successMessage"></span>
        </div>
    </div>
</div>

<script>
function gpsLocationTracker(updateInterval, showStatus, initialSharingEnabled) {
    return {
        updateInterval: updateInterval,
        showStatus: showStatus,
        locationStatus: 'inactive', // inactive, requesting, active, denied
        currentCoordinates: null,
        lastUpdateTime: null,
        locationSharingEnabled: initialSharingEnabled,
        errorMessage: null,
        successMessage: null,
        updateTimer: null,
        isUpdating: false,

        init() {
            console.log('GPS Tracker initialized with update interval:', this.updateInterval);
            this.checkLocationPermission();
            this.loadLocationSharingStatus();
        },

        async checkLocationPermission() {
            if (!navigator.geolocation) {
                this.locationStatus = 'denied';
                this.errorMessage = 'Geolocation is not supported by this browser.';
                return;
            }

            try {
                const permission = await navigator.permissions.query({ name: 'geolocation' });
                if (permission.state === 'granted') {
                    this.locationStatus = 'inactive'; // Don't auto-start, wait for user
                }
            } catch (error) {
                console.warn('Permission API not supported');
                this.locationStatus = 'inactive';
            }
        },

        async requestLocation() {
            if (this.isUpdating) return;
            
            console.log('Requesting location...');
            this.locationStatus = 'requesting';
            this.errorMessage = null;
            this.isUpdating = true;

            try {
                console.log('Getting current position...');
                const position = await this.getCurrentPosition();
                console.log('Position received:', position);
                this.handleLocationSuccess(position, false); // Manual update
            } catch (error) {
                console.error('Error requesting location:', error);
                this.handleLocationError(error);
            } finally {
                this.isUpdating = false;
            }
        },

        async updateLocationNow() {
            // This is the same as requestLocation, just call it directly
            await this.requestLocation();
        },

        getCurrentPosition() {
            return new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 60000 // Allow 1 minute old location
                });
            });
        },

        handleLocationSuccess(position, isAutomatic = true) {
            const { latitude, longitude } = position.coords;
            // Ensure lat/lng are numbers before calling toFixed
            const lat = parseFloat(latitude);
            const lng = parseFloat(longitude);
            this.currentCoordinates = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            this.lastUpdateTime = new Date().toLocaleTimeString();
            this.locationStatus = 'active';
            
            // Only show success message for manual updates
            if (!isAutomatic) {
                this.successMessage = 'Location shared successfully!';
                setTimeout(() => {
                    this.successMessage = null;
                }, 3000);
            }
            
            // Send to server
            this.sendLocationToServer(latitude, longitude);
            
            // Start periodic updates if location sharing is enabled and not already running
            if (this.locationSharingEnabled && !this.updateTimer) {
                this.startPeriodicUpdates();
            }
        },

        handleLocationError(error) {
            this.locationStatus = 'denied';
            this.stopPeriodicUpdates();
            
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    this.errorMessage = 'Location access denied. Please enable location sharing.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    this.errorMessage = 'Location information unavailable.';
                    break;
                case error.TIMEOUT:
                    this.errorMessage = 'Location request timed out.';
                    break;
                default:
                    this.errorMessage = 'An unknown error occurred while getting location.';
            }
        },

        startPeriodicUpdates() {
            // Clear any existing timer
            this.stopPeriodicUpdates();
            
            console.log('Starting periodic updates every', this.updateInterval, 'ms');
            this.updateTimer = setInterval(async () => {
                if (this.locationStatus === 'active' && this.locationSharingEnabled && !this.isUpdating) {
                    console.log('Automatic location update...');
                    this.isUpdating = true;
                    try {
                        const position = await this.getCurrentPosition();
                        this.handleLocationSuccess(position, true); // Automatic update
                    } catch (error) {
                        console.warn('Automatic update failed:', error);
                    } finally {
                        this.isUpdating = false;
                    }
                }
            }, this.updateInterval);
        },

        stopPeriodicUpdates() {
            if (this.updateTimer) {
                console.log('Stopping periodic updates');
                clearInterval(this.updateTimer);
                this.updateTimer = null;
            }
        },

        async sendLocationToServer(latitude, longitude) {
            try {
                const response = await fetch('/gps/update-location', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ latitude, longitude })
                });

                const data = await response.json();
                
                if (!data.success) {
                    console.error('Failed to send location to server:', data.error);
                }
            } catch (error) {
                console.error('Error sending location to server:', error);
            }
        },

        async toggleLocationSharing() {
            try {
                const response = await fetch('/gps/toggle-sharing', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ enabled: this.locationSharingEnabled })
                });

                const data = await response.json();
                
                if (data.success) {
                    // Update the hidden form field to sync with the component state
                    const hiddenField = document.querySelector('input[name="location_sharing_enabled"]');
                    if (hiddenField) {
                        hiddenField.value = this.locationSharingEnabled ? '1' : '0';
                    }
                    
                    if (this.locationSharingEnabled) {
                        this.requestLocation(); // Start tracking
                    } else {
                        // Immediately stop all tracking and reset status
                        this.stopPeriodicUpdates();
                        this.locationStatus = 'inactive';
                        this.currentCoordinates = null;
                        this.lastUpdateTime = null;
                        console.log('Location sharing disabled - stopped all tracking');
                    }
                }
            } catch (error) {
                console.error('Error toggling location sharing:', error);
            }
        },

        async loadLocationSharingStatus() {
            try {
                console.log('Loading location sharing status...');
                const response = await fetch('/gps/sharing-status');
                if (response.ok) {
                    const data = await response.json();
                    console.log('Location sharing status response:', data);
                    if (data.success) {
                        // Update the component state
                        this.locationSharingEnabled = data.location_sharing_enabled;
                        console.log('Location sharing enabled:', this.locationSharingEnabled);
                        
                        // Sync the hidden form field with the current state
                        const hiddenField = document.querySelector('input[name="location_sharing_enabled"]');
                        if (hiddenField) {
                            hiddenField.value = this.locationSharingEnabled ? '1' : '0';
                            console.log('Hidden field updated to:', hiddenField.value);
                        }
                        
                        if (data.has_location && data.location && this.locationSharingEnabled) {
                            // Only show location data if sharing is enabled
                            const lat = parseFloat(data.location.lat);
                            const lng = parseFloat(data.location.lng);
                            this.currentCoordinates = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                            this.lastUpdateTime = new Date(data.updated_at).toLocaleTimeString();
                            this.locationStatus = 'active';
                            console.log('Location data found and sharing enabled - status active');
                            this.startPeriodicUpdates();
                        } else if (this.locationSharingEnabled) {
                            // Sharing is enabled but no location data yet - request location to start tracking
                            console.log('Sharing enabled but no location data - requesting location...');
                            this.locationStatus = 'requesting';
                            this.requestLocation();
                        } else {
                            // Sharing is disabled - stop all tracking and show inactive
                            console.log('Sharing disabled - stopping all tracking and setting inactive');
                            this.stopPeriodicUpdates();
                            this.locationStatus = 'inactive';
                            this.currentCoordinates = null;
                            this.lastUpdateTime = null;
                        }
                    }
                }
            } catch (error) {
                console.warn('Could not load location sharing status:', error);
            }
        },

        getStatusText() {
            if (this.isUpdating) return 'Updating...';
            switch (this.locationStatus) {
                case 'active': return 'Active';
                case 'requesting': return 'Requesting...';
                case 'denied': return 'Denied';
                case 'inactive': return 'Inactive';
                default: return 'Unknown';
            }
        }
    }
}
</script>
@endif