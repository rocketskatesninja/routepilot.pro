@extends('layouts.app')

@section('title', 'Create Invoice')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Create New Invoice</h1>
            <p class="text-base-content/70 mt-2">Generate a new invoice for pool service</p>
        </div>
        <a href="{{ route('invoices.index') }}" class="btn btn-outline">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Invoices
        </a>
    </div>

    <!-- Invoice Form -->
    <div class="card bg-base-100 shadow-xl border border-base-300">
        <form action="{{ route('invoices.store') }}" method="POST" id="invoice-form" class="card-body p-6">
            @csrf
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Service Information -->
                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                        Service Information
                    </h3>
                    
                    <div>
                        <label for="client_search" class="block text-sm font-medium text-base-content mb-2">
                            Client <span class="text-error">*</span>
                        </label>
                        <input type="text" id="client_search" 
                               class="input input-bordered w-full @error('client_id') input-error @enderror" 
                               placeholder="Start typing client name..." 
                               autocomplete="off" autocorrect="off" spellcheck="false">
                        <input type="hidden" name="client_id" id="client_id" 
                               value="{{ old('client_id') }}" required>
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
                    
                    <div>
                        <label for="technician_id" class="block text-sm font-medium text-base-content mb-2">
                            Technician <span class="text-error">*</span>
                        </label>
                        <select name="technician_id" id="technician_id"
                                class="select select-bordered w-full @error('technician_id') select-error @enderror" 
                                required>
                            <option value="">Select Technician</option>
                            @foreach($technicians as $technician)
                                <option value="{{ $technician->id }}" {{ old('technician_id') == $technician->id ? 'selected' : '' }}>
                                    {{ $technician->full_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('technician_id')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="service_date" class="block text-sm font-medium text-base-content mb-2">
                                Service Date <span class="text-error">*</span>
                            </label>
                            <input type="date" name="service_date" id="service_date" 
                                   value="{{ old('service_date', date('Y-m-d')) }}" 
                                   class="input input-bordered w-full @error('service_date') input-error @enderror" 
                                   required>
                            @error('service_date')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label for="due_date" class="block text-sm font-medium text-base-content mb-2">
                                Due Date <span class="text-error">*</span>
                            </label>
                            <input type="date" name="due_date" id="due_date" 
                                   value="{{ old('due_date', date('Y-m-d', strtotime('+30 days'))) }}" 
                                   class="input input-bordered w-full @error('due_date') input-error @enderror" 
                                   required>
                            @error('due_date')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
                
                <!-- Cost Information -->
                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                        Cost Information
                    </h3>
                    
                    <div>
                        <label for="rate_per_visit" class="block text-sm font-medium text-base-content mb-2">
                            Rate per Visit <span class="text-error">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="rate_per_visit" id="rate_per_visit" 
                                   value="{{ old('rate_per_visit', '75.00') }}" step="0.01" min="0" 
                                   class="input input-bordered w-full @error('rate_per_visit') input-error @enderror" 
                                   required>
                        </div>
                        @error('rate_per_visit')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div class="form-control">
                        <label class="label cursor-pointer">
                            <span class="label-text">Include Chemicals</span>
                            <input type="checkbox" name="chemicals_included" id="chemicals_included" 
                                   value="1" {{ old('chemicals_included') ? 'checked' : '' }} 
                                   class="checkbox checkbox-primary">
                        </label>
                    </div>
                    
                    <div id="chemicals_cost_group" style="display: none;">
                        <label for="chemicals_cost" class="block text-sm font-medium text-base-content mb-2">
                            Chemicals Cost
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="chemicals_cost" id="chemicals_cost" 
                                   value="{{ old('chemicals_cost', '25.00') }}" step="0.01" min="0" 
                                   class="input input-bordered w-full @error('chemicals_cost') input-error @enderror">
                        </div>
                        @error('chemicals_cost')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label for="extras_cost" class="block text-sm font-medium text-base-content mb-2">
                            Extras Cost
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="extras_cost" id="extras_cost" 
                                   value="{{ old('extras_cost', '0.00') }}" step="0.01" min="0" 
                                   class="input input-bordered w-full @error('extras_cost') input-error @enderror">
                        </div>
                        @error('extras_cost')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <!-- Total Calculation -->
                    <div class="card bg-base-200 border border-base-300">
                        <div class="card-body">
                            <h4 class="card-title text-base-content">Total Calculation</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-base-content">Rate per Visit:</span>
                                    <span id="rate_display" class="text-base-content">$0.00</span>
                                </div>
                                <div class="flex justify-between" id="chemicals_display" style="display: none;">
                                    <span class="text-base-content">Chemicals Cost:</span>
                                    <span id="chemicals_display_amount" class="text-base-content">$0.00</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-base-content">Extras Cost:</span>
                                    <span id="extras_display" class="text-base-content">$0.00</span>
                                </div>
                                <div class="divider"></div>
                                <div class="flex justify-between font-bold">
                                    <span class="text-base-content">Total:</span>
                                    <span id="total_display" class="text-base-content">$0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Service Details -->
            <div class="mt-8 space-y-6">
                <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                    Service Details
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div>
                        <label for="service_type" class="block text-sm font-medium text-base-content mb-2">
                            Service Type <span class="text-error">*</span>
                        </label>
                        <select name="service_type" id="service_type" 
                                class="select select-bordered w-full @error('service_type') select-error @enderror" required>
                            <option value="">Select Service Type</option>
                            <option value="regular" {{ old('service_type') == 'regular' ? 'selected' : '' }}>Regular Service</option>
                            <option value="chemical" {{ old('service_type') == 'chemical' ? 'selected' : '' }}>Chemical Service</option>
                            <option value="repair" {{ old('service_type') == 'repair' ? 'selected' : '' }}>Repair Service</option>
                            <option value="cleaning" {{ old('service_type') == 'cleaning' ? 'selected' : '' }}>Deep Cleaning</option>
                            <option value="inspection" {{ old('service_type') == 'inspection' ? 'selected' : '' }}>Inspection</option>
                        </select>
                        @error('service_type')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label for="status" class="block text-sm font-medium text-base-content mb-2">
                            Status <span class="text-error">*</span>
                        </label>
                        <select name="status" id="status" 
                                class="select select-bordered w-full @error('status') select-error @enderror" required>
                            <option value="">Select Status</option>
                            <option value="draft" {{ old('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="sent" {{ old('status') == 'sent' ? 'selected' : '' }}>Sent</option>
                            <option value="paid" {{ old('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                            <option value="overdue" {{ old('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                            <option value="cancelled" {{ old('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                        @error('status')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                
                <div>
                    <label for="service_notes" class="block text-sm font-medium text-base-content mb-2">
                        Service Notes
                    </label>
                    <textarea name="service_notes" id="service_notes" rows="4" 
                              class="textarea textarea-bordered w-full @error('service_notes') textarea-error @enderror"
                              placeholder="Describe the services performed...">{{ old('service_notes') }}</textarea>
                    @error('service_notes')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-4">
                <a href="{{ route('invoices.index') }}" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Create Invoice
                </button>
            </div>
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

    // Chemicals calculation functionality
    const chemicalsCheckbox = document.getElementById('chemicals_included');
    const chemicalsGroup = document.getElementById('chemicals_cost_group');
    const chemicalsDisplay = document.getElementById('chemicals_display');
    const rateInput = document.getElementById('rate_per_visit');
    const chemicalsInput = document.getElementById('chemicals_cost');
    const extrasInput = document.getElementById('extras_cost');
    
    // Toggle chemicals cost field
    chemicalsCheckbox.addEventListener('change', function() {
        if (this.checked) {
            chemicalsGroup.style.display = 'block';
            chemicalsDisplay.style.display = 'flex';
        } else {
            chemicalsGroup.style.display = 'none';
            chemicalsDisplay.style.display = 'none';
            chemicalsInput.value = '0.00';
        }
        updateTotal();
    });
    
    // Update total calculation
    function updateTotal() {
        const rate = parseFloat(rateInput.value) || 0;
        const chemicals = chemicalsCheckbox.checked ? (parseFloat(chemicalsInput.value) || 0) : 0;
        const extras = parseFloat(extrasInput.value) || 0;
        const total = rate + chemicals + extras;
        
        document.getElementById('rate_display').textContent = '$' + rate.toFixed(2);
        document.getElementById('chemicals_display_amount').textContent = '$' + chemicals.toFixed(2);
        document.getElementById('extras_display').textContent = '$' + extras.toFixed(2);
        document.getElementById('total_display').textContent = '$' + total.toFixed(2);
    }
    
    // Add event listeners for real-time calculation
    rateInput.addEventListener('input', updateTotal);
    chemicalsInput.addEventListener('input', updateTotal);
    extrasInput.addEventListener('input', updateTotal);
    
    // Initialize calculation
    updateTotal();

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
});
</script>
@endsection 