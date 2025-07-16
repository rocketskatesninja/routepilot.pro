@extends('layouts.app')

@section('title', 'Edit Service Report')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Edit Service Report</h1>
            <p class="text-base-content/70 mt-2">Update service report details</p>
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
        <form action="{{ route('reports.update', $report) }}" method="POST" enctype="multipart/form-data" id="report-form" class="card-body p-6">
            @csrf
            @method('PUT')

            @if(isset($technicians))
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Basic Information -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                            Basic Information
                        </h3>
                        
                        <div>
                            <label for="client_search" class="block text-sm font-medium text-base-content mb-2">
                                Client <span class="text-error">*</span>
                            </label>
                            <input type="text" id="client_search" 
                                   class="input input-bordered w-full @error('client_id') input-error @enderror" 
                                   placeholder="Start typing client name..." 
                                   value="{{ $report->client->full_name }} - {{ $report->client->email }}"
                                   autocomplete="off">
                            <input type="hidden" name="client_id" id="client_id" 
                                   value="{{ old('client_id', $report->client_id) }}" required>
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
                                    required>
                                <option value="">Select Location</option>
                                <option value="{{ $report->location->id }}" selected>
                                    {{ $report->location->nickname ?? 'Location' }} - {{ $report->location->full_address }}
                                </option>
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
                                       value="{{ old('service_date', $report->service_date ? $report->service_date->format('Y-m-d') : '') }}" 
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
                                       value="{{ old('service_time', $report->service_time ? $report->service_time->format('H:i') : '') }}" 
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
                                    <option value="{{ $tech->id }}" {{ (old('technician_id', $report->technician_id) == $tech->id) ? 'selected' : '' }}>
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
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="fac" class="block text-sm font-medium text-base-content mb-2">Chlorine</label>
                                <input type="number" step="0.01" name="fac" id="fac" 
                                       value="{{ old('fac', $report->fac) }}" 
                                       class="input input-bordered w-full @error('fac') input-error @enderror">
                                @error('fac')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="salt" class="block text-sm font-medium text-base-content mb-2">Salt</label>
                                <input type="number" name="salt" id="salt" 
                                       value="{{ old('salt', $report->salt) }}" 
                                       class="input input-bordered w-full @error('salt') input-error @enderror">
                                @error('salt')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="ph" class="block text-sm font-medium text-base-content mb-2">pH</label>
                                <input type="number" step="0.1" name="ph" id="ph" 
                                       value="{{ old('ph', $report->ph) }}" 
                                       class="input input-bordered w-full @error('ph') input-error @enderror">
                                @error('ph')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="alkalinity" class="block text-sm font-medium text-base-content mb-2">Alkalinity</label>
                                <input type="number" name="alkalinity" id="alkalinity" 
                                       value="{{ old('alkalinity', $report->alkalinity) }}" 
                                       class="input input-bordered w-full @error('alkalinity') input-error @enderror">
                                @error('alkalinity')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('secondary-chemistry').classList.toggle('hidden')">
                            Secondary Measurements
                        </button>
                        
                        <div id="secondary-chemistry" class="grid grid-cols-2 gap-4 {{ $report->calcium || $report->tds || $report->cya ? '' : 'hidden' }}">
                            <div>
                                <label for="calcium" class="block text-sm font-medium text-base-content mb-2">Calcium</label>
                                <input type="number" name="calcium" id="calcium" 
                                       value="{{ old('calcium', $report->calcium) }}" 
                                       class="input input-bordered w-full @error('calcium') input-error @enderror">
                                @error('calcium')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="tds" class="block text-sm font-medium text-base-content mb-2">TDS</label>
                                <input type="number" name="tds" id="tds" 
                                       value="{{ old('tds', $report->tds) }}" 
                                       class="input input-bordered w-full @error('tds') input-error @enderror">
                                @error('tds')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="cya" class="block text-sm font-medium text-base-content mb-2">CYA</label>
                                <input type="number" name="cya" id="cya" 
                                       value="{{ old('cya', $report->cya) }}" 
                                       class="input input-bordered w-full @error('cya') input-error @enderror">
                                @error('cya')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
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
                                           {{ old($task, $report->$task) ? 'checked' : '' }}>
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
                                           {{ old($task, $report->$task) ? 'checked' : '' }}>
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
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div class="space-y-4">
                            <div>
                                <label for="chemicals_used" class="block text-sm font-medium text-base-content mb-2">
                                    Chemicals Used
                                </label>
                                <input type="text" name="chemicals_used" id="chemicals_used" 
                                       value="{{ old('chemicals_used', is_array($report->chemicals_used) ? implode(', ', $report->chemicals_used) : $report->chemicals_used) }}" 
                                       placeholder="e.g. Chlorine, Acid" 
                                       class="input input-bordered w-full @error('chemicals_used') input-error @enderror">
                                @error('chemicals_used')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label for="chemicals_cost" class="block text-sm font-medium text-base-content mb-2">
                                    Chemicals Cost <span class="text-xs text-base-content/60">(strikethrough if flat-rate client)</span>
                                </label>
                                <input type="number" step="0.01" name="chemicals_cost" id="chemicals_cost" 
                                       value="{{ old('chemicals_cost', $report->chemicals_cost) }}" 
                                       class="input input-bordered w-full @error('chemicals_cost') input-error @enderror">
                                @error('chemicals_cost')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <div>
                                <label for="other_services" class="block text-sm font-medium text-base-content mb-2">
                                    Other Services
                                </label>
                                <input type="text" name="other_services" id="other_services" 
                                       value="{{ old('other_services', is_array($report->other_services) ? implode(', ', $report->other_services) : $report->other_services) }}" 
                                       placeholder="e.g. Filter Clean" 
                                       class="input input-bordered w-full @error('other_services') input-error @enderror">
                                @error('other_services')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label for="other_services_cost" class="block text-sm font-medium text-base-content mb-2">
                                    Other Costs
                                </label>
                                <input type="number" step="0.01" name="other_services_cost" id="other_services_cost" 
                                       value="{{ old('other_services_cost', $report->other_services_cost) }}" 
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
                                      placeholder="Notes visible to the client...">{{ old('notes_to_client', $report->notes_to_client) }}</textarea>
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
                                      placeholder="Internal notes for admin...">{{ old('notes_to_admin', $report->notes_to_admin) }}</textarea>
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
                        
                        @if(!empty($report->photos))
                            <div class="flex flex-wrap gap-2 mt-4">
                                @foreach($report->photos as $photo)
                                    <img src="{{ Storage::url($photo) }}" alt="Report Photo" 
                                         class="w-24 h-24 object-cover rounded border border-base-300">
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Invoice Generation -->
                <div class="mt-8 space-y-6">
                    <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                        Invoice Generation
                    </h3>
                    
                    <div class="form-control">
                        <label class="label cursor-pointer">
                            <input type="checkbox" name="generate_invoice" id="generate_invoice" 
                                   class="checkbox checkbox-primary" 
                                   {{ old('generate_invoice') ? 'checked' : '' }}>
                            <span class="label-text ml-2">Automatically generate invoice for this report</span>
                        </label>
                        <p class="text-sm text-base-content/70 mt-2">
                            When checked, an invoice will be created automatically using the service details and costs from this report.
                        </p>
                        @if($report->invoice)
                            <div class="alert alert-info mt-4">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                This report already has an associated invoice: 
                                <a href="{{ route('invoices.show', $report->invoice) }}" class="font-medium hover:underline">
                                    {{ $report->invoice->invoice_number }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="mt-8 flex justify-end space-x-4">
                    <a href="{{ route('reports.show', $report) }}" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Update Report
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

    clientSearch.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            clientSuggestions.style.display = 'none';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            fetch(`/api/clients/search?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    clientSuggestions.innerHTML = '';
                    
                    if (data.length === 0) {
                        clientSuggestions.innerHTML = '<div class="p-3 text-base-content/70">No clients found</div>';
                    } else {
                        data.forEach(client => {
                            const div = document.createElement('div');
                            div.className = 'p-3 hover:bg-base-200 cursor-pointer border-b border-base-300 last:border-b-0';
                            div.textContent = `${client.full_name} - ${client.email}`;
                            div.addEventListener('click', () => {
                                clientSearch.value = `${client.full_name} - ${client.email}`;
                                clientId.value = client.id;
                                clientSuggestions.style.display = 'none';
                                
                                // Load locations for selected client
                                loadClientLocations(client.id);
                            });
                            clientSuggestions.appendChild(div);
                        });
                    }
                    
                    clientSuggestions.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error fetching clients:', error);
                });
        }, 300);
    });

    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!clientSearch.contains(e.target) && !clientSuggestions.contains(e.target)) {
            clientSuggestions.style.display = 'none';
        }
    });

    // Load locations for selected client
    function loadClientLocations(clientId) {
        locationSelect.innerHTML = '<option value="">Loading locations...</option>';
        locationSelect.disabled = true;
        
        fetch(`/api/clients/${clientId}/locations`)
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
            })
            .catch(error => {
                console.error('Error fetching locations:', error);
                locationSelect.innerHTML = '<option value="">Error loading locations</option>';
                locationSelect.disabled = true;
            });
    }

    // Clear location dropdown when client search is cleared
    clientSearch.addEventListener('input', function() {
        if (this.value.trim() === '') {
            clientId.value = '';
            locationSelect.innerHTML = '<option value="">Select a client first</option>';
            locationSelect.disabled = true;
        }
    });
});
</script>
@endsection 