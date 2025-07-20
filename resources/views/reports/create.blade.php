@extends('layouts.app')

@section('title', 'Add Service Report')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Add Service Report</h1>
            <p class="text-base-content/70 mt-2">Create a new service report for a client location</p>
        </div>
        <a href="{{ route('reports.index') }}" class="btn btn-outline">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Reports
        </a>
    </div>

    <!-- Service Report Form -->
    <div class="card bg-base-100 shadow-xl border border-base-300">
        <form action="{{ isset($report) ? route('reports.update', $report) : route('reports.store') }}" method="POST" enctype="multipart/form-data" id="report-form" class="card-body p-6">
            @csrf
            @if(isset($report))
                @method('PUT')
            @endif

            @if(isset($technicians))
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Basic Information -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                            Basic Information
                        </h3>
                        
                        <div class="relative">
                            <label for="client_search" class="block text-sm font-medium text-base-content mb-2">
                                Client <span class="text-error">*</span>
                            </label>
                            <input type="text" id="client_search" 
                                   class="input input-bordered w-full @error('client_id') input-error @enderror" 
                                   placeholder="Start typing client name..." 
                                   autocomplete="off" autocorrect="off" spellcheck="false">
                            <input type="hidden" name="client_id" id="client_id" 
                                   value="{{ old('client_id', $report->client_id ?? '') }}" required>
                            @if(isset($locationId))
                                <input type="hidden" id="preset_location_id" value="{{ $locationId }}">
                            @endif
                            <div id="client_suggestions" class="absolute z-50 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg max-h-60 overflow-y-auto" style="display: none;"></div>
                            @error('client_id')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="location_id" class="block text-sm font-medium text-base-content mb-2">
                                Location <span class="text-error">*</span>
                            </label>
                            <select name="location_id" id="location_id" 
                                    class="select select-bordered w-full @error('location_id') select-error @enderror" 
                                    required disabled>
                                <option value="">Select a client first</option>
                            </select>
                            @error('location_id')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="service_date" class="block text-sm font-medium text-base-content mb-2">
                                    Service Date <span class="text-error">*</span>
                                </label>
                                <input type="date" name="service_date" id="service_date" 
                                       value="{{ old('service_date', $report->service_date ?? date('Y-m-d')) }}" 
                                       class="input input-bordered w-full @error('service_date') input-error @enderror" required>
                                @error('service_date')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label for="service_time" class="block text-sm font-medium text-base-content mb-2">
                                    Service Time <span class="text-error">*</span>
                                </label>
                                <input type="time" name="service_time" id="service_time" 
                                       value="{{ old('service_time', $report->service_time ?? date('H:i')) }}" 
                                       class="input input-bordered w-full @error('service_time') input-error @enderror" required>
                                @error('service_time')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="technician_id" class="block text-sm font-medium text-base-content mb-2">
                                Technician <span class="text-error">*</span>
                            </label>
                            <select name="technician_id" id="technician_id" 
                                    class="select select-bordered w-full @error('technician_id') select-error @enderror" required>
                                <option value="">Select Technician</option>
                                @foreach($technicians as $tech)
                                    <option value="{{ $tech->id }}" {{ (old('technician_id', $report->technician_id ?? auth()->id()) == $tech->id) ? 'selected' : '' }}>
                                        {{ $tech->full_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('technician_id')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Chemistry Readings -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                            Chemistry Readings
                        </h3>
                        
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <label for="fac" class="block text-sm font-medium text-base-content mb-2">Chlorine</label>
                                <input type="number" step="0.01" name="fac" id="fac" 
                                       value="{{ old('fac', $report->fac ?? '') }}" 
                                       class="input input-bordered w-full @error('fac') input-error @enderror">
                                @error('fac')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="salt" class="block text-sm font-medium text-base-content mb-2">Salt</label>
                                <input type="number" name="salt" id="salt" 
                                       value="{{ old('salt', $report->salt ?? '') }}" 
                                       class="input input-bordered w-full @error('salt') input-error @enderror">
                                @error('salt')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="ph" class="block text-sm font-medium text-base-content mb-2">pH</label>
                                <input type="number" step="0.1" name="ph" id="ph" 
                                       value="{{ old('ph', $report->ph ?? '') }}" 
                                       class="input input-bordered w-full @error('ph') input-error @enderror">
                                @error('ph')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="alkalinity" class="block text-sm font-medium text-base-content mb-2">Alkalinity</label>
                                <input type="number" name="alkalinity" id="alkalinity" 
                                       value="{{ old('alkalinity', $report->alkalinity ?? '') }}" 
                                       class="input input-bordered w-full @error('alkalinity') input-error @enderror">
                                @error('alkalinity')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="calcium" class="block text-sm font-medium text-base-content mb-2">Calcium</label>
                                <input type="number" name="calcium" id="calcium" 
                                       value="{{ old('calcium', $report->calcium ?? '') }}" 
                                       class="input input-bordered w-full @error('calcium') input-error @enderror">
                                @error('calcium')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="tds" class="block text-sm font-medium text-base-content mb-2">TDS</label>
                                <input type="number" name="tds" id="tds" 
                                       value="{{ old('tds', $report->tds ?? '') }}" 
                                       class="input input-bordered w-full @error('tds') input-error @enderror">
                                @error('tds')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="cya" class="block text-sm font-medium text-base-content mb-2">CYA</label>
                                <input type="number" name="cya" id="cya" 
                                       value="{{ old('cya', $report->cya ?? '') }}" 
                                       class="input input-bordered w-full @error('cya') input-error @enderror">
                                @error('cya')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        
                        <!-- Chemical Calculator Integration -->
                        <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3">
                            <span class="text-base-content">Send chemistry readings to chemical calculator</span>
                            <input type="checkbox" name="send_to_calculator" id="send_to_calculator" 
                                   class="checkbox checkbox-primary" 
                                   {{ old('send_to_calculator', true) ? 'checked' : '' }}>
                        </div>
                    </div>
                </div>

                <!-- Tasks Checklist -->
                <div class="mt-8 space-y-6">
                    <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                        Tasks Checklist
                    </h3>
                    
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @foreach(['vacuumed','brushed','skimmed','cleaned_skimmer_basket','cleaned_pump_basket','cleaned_pool_deck'] as $task)
                            <div class="form-control">
                                <label class="label cursor-pointer">
                                    <input type="checkbox" name="{{ $task }}" id="{{ $task }}" 
                                           class="checkbox checkbox-primary" 
                                           {{ old($task, $report->$task ?? false) ? 'checked' : '' }}>
                                    <span class="label-text ml-2 capitalize">{{ str_replace('_', ' ', $task) }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Maintenance Checklist -->
                <div class="mt-8 space-y-6">
                    <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                        Maintenance Checklist
                    </h3>
                    
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @foreach(['cleaned_filter_cartridge','backwashed_sand_filter','adjusted_water_level','adjusted_auto_fill','adjusted_pump_timer','adjusted_heater','checked_cover','checked_lights','checked_fountain','checked_heater'] as $task)
                            <div class="form-control">
                                <label class="label cursor-pointer">
                                    <input type="checkbox" name="{{ $task }}" id="{{ $task }}" 
                                           class="checkbox checkbox-primary" 
                                           {{ old($task, $report->$task ?? false) ? 'checked' : '' }}>
                                    <span class="label-text ml-2 capitalize">{{ str_replace('_', ' ', $task) }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Service Details -->
                <div class="mt-8 space-y-6">
                    <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                        Service Details
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="flex gap-4">
                            <div class="flex-1">
                                <label for="chemicals_used" class="block text-sm font-medium text-base-content mb-2">
                                    Chemicals Used
                                </label>
                                <input type="text" name="chemicals_used" id="chemicals_used" 
                                       value="{{ old('chemicals_used', $report->chemicals_used ?? '') }}" 
                                       placeholder="e.g. Chlorine, Acid" 
                                       class="input input-bordered w-full @error('chemicals_used') input-error @enderror">
                                @error('chemicals_used')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div class="w-24">
                                <label for="chemicals_cost" class="block text-sm font-medium text-base-content mb-2">
                                    Cost
                                </label>
                                <input type="number" step="0.01" name="chemicals_cost" id="chemicals_cost" 
                                       value="{{ old('chemicals_cost', '') }}" 
                                       class="input input-bordered w-full @error('chemicals_cost') input-error @enderror">
                                @error('chemicals_cost')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="flex gap-4">
                            <div class="flex-1">
                                <label for="other_services" class="block text-sm font-medium text-base-content mb-2">
                                    Other Services
                                </label>
                                <input type="text" name="other_services" id="other_services" 
                                       value="{{ old('other_services', $report->other_services ?? '') }}" 
                                       placeholder="e.g. Filter Clean" 
                                       class="input input-bordered w-full @error('other_services') input-error @enderror">
                                @error('other_services')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div class="w-24">
                                <label for="other_services_cost" class="block text-sm font-medium text-base-content mb-2">
                                    Cost
                                </label>
                                <input type="number" step="0.01" name="other_services_cost" id="other_services_cost" 
                                       value="{{ old('other_services_cost', $report->other_services_cost ?? '') }}" 
                                       class="input input-bordered w-full @error('other_services_cost') input-error @enderror">
                                @error('other_services_cost')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="mt-8 space-y-6">
                    <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                        Notes
                    </h3>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div>
                            <label for="notes_to_client" class="block text-sm font-medium text-base-content mb-2">
                                Customer Notes
                            </label>
                            <textarea name="notes_to_client" id="notes_to_client" rows="4" 
                                      class="textarea textarea-bordered w-full @error('notes_to_client') textarea-error @enderror"
                                      placeholder="Notes visible to the client...">{{ old('notes_to_client', $report->notes_to_client ?? '') }}</textarea>
                            @error('notes_to_client')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label for="notes_to_admin" class="block text-sm font-medium text-base-content mb-2">
                                Admin Notes
                            </label>
                            <textarea name="notes_to_admin" id="notes_to_admin" rows="4" 
                                      class="textarea textarea-bordered w-full @error('notes_to_admin') textarea-error @enderror"
                                      placeholder="Internal notes for admin...">{{ old('notes_to_admin', $report->notes_to_admin ?? '') }}</textarea>
                            @error('notes_to_admin')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Photo Management -->
                <div class="mt-8 space-y-6">
                    <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                        Photo Management
                    </h3>
                    
                    <div>
                        <label for="photos" class="block text-sm font-medium text-base-content mb-2">
                            Photos
                        </label>
                        <input type="file" name="photos[]" id="photos" 
                               class="file-input file-input-bordered w-full @error('photos') file-input-error @enderror" 
                               multiple accept="image/*">
                        @error('photos')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                        
                        @if(isset($report) && !empty($report->photos))
                            <div class="flex flex-wrap gap-2 mt-4">
                                @foreach($report->photos as $photo)
                                    <img src="{{ Storage::url($photo) }}" alt="Report Photo" 
                                         class="w-24 h-24 object-cover rounded border border-base-300">
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Notification Settings and Invoice Generation -->
                <div class="mt-8 space-y-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div class="space-y-6">
                            <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                                Notification Settings
                            </h3>
                            
                            <div class="space-y-4">
                                <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3">
                                    <span class="text-base-content">Notify client (pre-checked if client allows)</span>
                                    <input type="checkbox" name="notify_client" id="notify_client" 
                                           class="checkbox checkbox-primary" 
                                           {{ (isset($client) && $client->service_reports) ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                                Invoice Generation
                            </h3>
                            
                            <div class="space-y-4">
                                <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3">
                                    <span class="text-base-content">Automatically generate invoice for this report</span>
                                    <input type="checkbox" name="generate_invoice" id="generate_invoice" 
                                           class="checkbox checkbox-primary" 
                                           {{ old('generate_invoice', true) ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="mt-8 flex justify-end space-x-4">
                    <a href="{{ route('reports.index') }}" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="submit-button">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        <span id="button-text">{{ isset($report) ? 'Save Changes' : 'Create Report' }}</span>
                    </button>
                </div>
            @else
                <div class="alert alert-error">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                    One or more required variables are missing. Please check the controller logic.
                </div>
            @endif
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Client autocomplete functionality
    const clientSearch = document.getElementById('client_search');
    const clientId = document.getElementById('client_id');
    const clientSuggestions = document.getElementById('client_suggestions');
    const locationSelect = document.getElementById('location_id');
    let searchTimeout;
    let clientsList = [];
    let activeIndex = -1;
    let lastValue = '';

    // Prevent browser autofill
    clientSearch.setAttribute('autocomplete', 'off');
    clientSearch.setAttribute('autocorrect', 'off');
    clientSearch.setAttribute('spellcheck', 'false');

    clientSearch.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        lastValue = query;
        activeIndex = -1;
        if (query.length < 2) {
            clientSuggestions.style.display = 'none';
            return;
        }
        searchTimeout = setTimeout(() => {
            fetch(`/api/clients/search?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    clientsList = data;
                    clientSuggestions.innerHTML = '';
                    if (data.length === 0) {
                        clientSuggestions.innerHTML = '<div class="p-3 text-base-content/70">No clients found</div>';
                        clientId.value = '';
                    } else {
                        data.forEach((client, idx) => {
                            const div = document.createElement('div');
                            div.className = 'p-3 hover:bg-base-200 cursor-pointer border-b border-base-300 last:border-b-0';
                            div.textContent = `${client.full_name} - ${client.email}`;
                            div.addEventListener('mousedown', () => {
                                clientSearch.value = client.full_name;
                                clientId.value = client.id;
                                clientSuggestions.style.display = 'none';
                                clientSearch.setSelectionRange(client.full_name.length, client.full_name.length);
                                loadClientLocations(client.id);
                            });
                            clientSuggestions.appendChild(div);
                        });
                        // Inline autocomplete: if top suggestion starts with input, fill it
                        const top = data[0];
                        if (top && top.full_name.toLowerCase().startsWith(query.toLowerCase()) && top.full_name.length > query.length) {
                            clientSearch.value = top.full_name;
                            clientSearch.setSelectionRange(query.length, top.full_name.length);
                            clientId.value = top.id;
                        } else {
                            clientId.value = '';
                        }
                    }
                    clientSuggestions.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error fetching clients:', error);
                });
        }, 300);
    });

    // Accept autocomplete with Tab or Right Arrow
    clientSearch.addEventListener('keydown', function(e) {
        if ((e.key === 'Tab' || e.key === 'ArrowRight') && this.selectionEnd > this.value.length - 1) {
            this.setSelectionRange(this.value.length, this.value.length);
            clientSuggestions.style.display = 'none';
            e.preventDefault();
        } else if (e.key === 'ArrowDown') {
            const items = clientSuggestions.querySelectorAll('div');
            if (items.length > 0) {
                activeIndex = (activeIndex + 1) % items.length;
                items.forEach((el, idx) => el.classList.toggle('bg-base-200', idx === activeIndex));
                if (activeIndex >= 0) {
                    clientSearch.value = items[activeIndex].textContent.split(' - ')[0];
                    clientId.value = clientsList[activeIndex].id;
                }
            }
            e.preventDefault();
        } else if (e.key === 'ArrowUp') {
            const items = clientSuggestions.querySelectorAll('div');
            if (items.length > 0) {
                activeIndex = (activeIndex - 1 + items.length) % items.length;
                items.forEach((el, idx) => el.classList.toggle('bg-base-200', idx === activeIndex));
                if (activeIndex >= 0) {
                    clientSearch.value = items[activeIndex].textContent.split(' - ')[0];
                    clientId.value = clientsList[activeIndex].id;
                }
            }
            e.preventDefault();
        } else if (e.key === 'Enter') {
            const items = clientSuggestions.querySelectorAll('div');
            if (activeIndex >= 0 && items[activeIndex]) {
                clientSearch.value = items[activeIndex].textContent.split(' - ')[0];
                clientId.value = clientsList[activeIndex].id;
                clientSuggestions.style.display = 'none';
                this.setSelectionRange(this.value.length, this.value.length);
                e.preventDefault();
            }
        } else if (e.key === 'Escape') {
            clientSuggestions.style.display = 'none';
        }
    });

    // Hide suggestions when clicking outside
    document.addEventListener('mousedown', function(e) {
        if (!clientSearch.contains(e.target) && !clientSuggestions.contains(e.target)) {
            clientSuggestions.style.display = 'none';
        }
    });

    // Load locations for selected client
    function loadClientLocations(clientId) {
        if (!locationSelect) return;
        locationSelect.innerHTML = '<option value="">Loading locations...</option>';
        locationSelect.disabled = true;
        return fetch(`/api/clients/${clientId}/locations`)
            .then(response => response.json())
            .then(locations => {
                locationSelect.innerHTML = '<option value="">Select Location</option>';
                if (locations.length === 0) {
                    locationSelect.innerHTML = '<option value="">No locations found for this client</option>';
                } else {
                    locations.forEach(location => {
                        const option = document.createElement('option');
                        option.value = location.id;
                        option.textContent = location.display_name;
                        locationSelect.appendChild(option);
                    });
                }
                locationSelect.disabled = false;
                return locations;
            })
            .catch(error => {
                console.error('Error fetching locations:', error);
                locationSelect.innerHTML = '<option value="">Error loading locations</option>';
                locationSelect.disabled = true;
                throw error;
            });
    }

    // Clear location dropdown when client search is cleared
    clientSearch.addEventListener('input', function() {
        if (this.value.trim() === '') {
            clientId.value = '';
            if (locationSelect) {
            locationSelect.innerHTML = '<option value="">Select a client first</option>';
            locationSelect.disabled = true;
            }
        }
    });

    // Handle preset location_id if provided
    const presetLocationId = document.getElementById('preset_location_id');
    if (presetLocationId && presetLocationId.value) {
        // Fetch location details to get client info
        fetch(`/api/locations/${presetLocationId.value}`)
            .then(response => response.json())
            .then(location => {
                if (location.client) {
                    // Set client search and ID
                    clientSearch.value = `${location.client.full_name} - ${location.client.email}`;
                    clientId.value = location.client.id;
                    
                    // Load locations and select the preset one
                    loadClientLocations(location.client.id).then(() => {
                        locationSelect.value = presetLocationId.value;
                        
                        // Auto-populate technician if location has an assigned technician
                        if (location.assigned_technician) {
                            const technicianSelect = document.getElementById('technician_id');
                            if (technicianSelect) {
                                technicianSelect.value = location.assigned_technician.id;
                            }
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error fetching location details:', error);
            });
    }

    // Disable chemicals_cost field if flat-rate location
    const chemicalsCostInput = document.getElementById('chemicals_cost');
    const locationIdInput = document.getElementById('location_id');

    if (chemicalsCostInput && locationIdInput) {
        locationIdInput.addEventListener('change', function() {
            if (this.value) {
                // Fetch location details to check if chemicals are included
                fetch(`/api/locations/${this.value}`)
                    .then(response => response.json())
                    .then(location => {
                        if (location.chemicals_included) {
                            chemicalsCostInput.disabled = true;
                            chemicalsCostInput.value = '0.00';
                            chemicalsCostInput.classList.add('opacity-50');
                        } else {
                            chemicalsCostInput.disabled = false;
                            chemicalsCostInput.classList.remove('opacity-50');
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching location details:', error);
                        chemicalsCostInput.disabled = false;
                        chemicalsCostInput.classList.remove('opacity-50');
                    });
            } else {
                chemicalsCostInput.disabled = true;
                chemicalsCostInput.value = '';
                chemicalsCostInput.classList.add('opacity-50');
            }
        });

        // Initial check on page load
        if (locationIdInput.value) {
            fetch(`/api/locations/${locationIdInput.value}`)
                .then(response => response.json())
                .then(location => {
                    if (location.chemicals_included) {
                        chemicalsCostInput.disabled = true;
                        chemicalsCostInput.value = '0.00';
                        chemicalsCostInput.classList.add('opacity-50');
                    } else {
                        chemicalsCostInput.disabled = false;
                        chemicalsCostInput.classList.remove('opacity-50');
                    }
                })
                .catch(error => {
                    console.error('Error fetching location details:', error);
                    chemicalsCostInput.disabled = false;
                    chemicalsCostInput.classList.remove('opacity-50');
                });
        } else {
            chemicalsCostInput.disabled = true;
            chemicalsCostInput.classList.add('opacity-50');
        }
    }

    // Handle chemical calculator checkbox
    const calculatorCheckbox = document.getElementById('send_to_calculator');
    const buttonText = document.getElementById('button-text');
    const isEdit = {{ isset($report) ? 'true' : 'false' }};

    if (calculatorCheckbox && buttonText) {
        function updateButtonText() {
            if (calculatorCheckbox.checked) {
                buttonText.textContent = isEdit ? 'Save Changes / Open Calculator' : 'Create Report / Open Calculator';
            } else {
                buttonText.textContent = isEdit ? 'Save Changes' : 'Create Report';
            }
        }

        // Initial state
        updateButtonText();

        // Listen for changes
        calculatorCheckbox.addEventListener('change', updateButtonText);
    }
});
</script>
@endsection 