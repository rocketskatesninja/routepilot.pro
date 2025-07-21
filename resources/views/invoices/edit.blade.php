@extends('layouts.app')

@section('title', 'Edit Invoice')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Edit Invoice #{{ $invoice->invoice_number }}</h1>
            <p class="text-base-content/70 mt-2">Update invoice details</p>
        </div>
        <div class="flex space-x-2">
            <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                View Invoice
            </a>
            @if(auth()->user()->isAdmin() || auth()->user()->isTechnician())
            <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this invoice? This action cannot be undone.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn bg-red-600 hover:bg-red-700 text-white border-red-600">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete Invoice
                </button>
            </form>
            @endif
        </div>
    </div>

    <!-- Invoice Form -->
    <div class="card bg-base-100 shadow-xl border border-base-300">
        <form action="{{ route('invoices.update', $invoice) }}" method="POST" id="invoice-form" class="card-body p-6">
            @csrf
            @method('PUT')
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Service Information -->
                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                        Service Information
                    </h3>
                    
                    <div class="relative">
                        <label for="client_search" class="block text-sm font-medium text-base-content mb-2">
                            Client <span class="text-error">*</span>
                        </label>
                        <input type="text" id="client_search" 
                               class="input input-bordered w-full @error('client_id') input-error @enderror" 
                               placeholder="Start typing client name..." 
                               value="{{ $invoice->client->full_name }}"
                               autocomplete="off">
                        <input type="hidden" name="client_id" id="client_id" 
                               value="{{ old('client_id', $invoice->client_id) }}" required>
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
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" {{ old('location_id', $invoice->location_id) == $location->id ? 'selected' : '' }}>
                                    {{ $location->nickname ?? 'Location' }} - {{ $location->client->full_name }}
                                </option>
                            @endforeach
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
                                <option value="{{ $technician->id }}" {{ old('technician_id', $invoice->technician_id) == $technician->id ? 'selected' : '' }}>
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
                                   value="{{ old('service_date', $invoice->service_date->format('Y-m-d')) }}" 
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
                                   value="{{ old('due_date', $invoice->due_date->format('Y-m-d')) }}" 
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
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="rate_per_visit" class="block text-sm font-medium text-base-content mb-2">
                                Rate per Visit <span class="text-error">*</span>
                            </label>
                            <input type="number" name="rate_per_visit" id="rate_per_visit" 
                                   value="{{ old('rate_per_visit', number_format($invoice->rate_per_visit, 2)) }}" step="0.01" min="0" 
                                   class="input input-bordered w-full @error('rate_per_visit') input-error @enderror" 
                                   required>
                            @error('rate_per_visit')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label for="extras_cost" class="block text-sm font-medium text-base-content mb-2">
                                Extras Cost
                            </label>
                            <input type="number" name="extras_cost" id="extras_cost" 
                                   value="{{ old('extras_cost', number_format($invoice->extras_cost, 2)) }}" step="0.01" min="0" 
                                   class="input input-bordered w-full @error('extras_cost') input-error @enderror">
                            @error('extras_cost')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex items-end">
                            <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3 w-full">
                                <span class="text-base-content">Include Chemicals</span>
                                <input type="checkbox" name="chemicals_included" id="chemicals_included" 
                                       value="1" {{ old('chemicals_included', $invoice->chemicals_included) ? 'checked' : '' }} 
                                       class="checkbox checkbox-primary">
                            </div>
                        </div>
                        
                        <div>
                            <label for="chemicals_cost" class="block text-sm font-medium text-base-content mb-2">
                                Chemicals Cost
                            </label>
                            <input type="number" name="chemicals_cost" id="chemicals_cost" 
                                   value="{{ old('chemicals_cost', number_format($invoice->chemicals_cost, 2)) }}" step="0.01" min="0" 
                                   class="input input-bordered w-full @error('chemicals_cost') input-error @enderror">
                            @error('chemicals_cost')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
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
                        <label for="status" class="block text-sm font-medium text-base-content mb-2">
                            Status <span class="text-error">*</span>
                        </label>
                        <select name="status" id="status" 
                                class="select select-bordered w-full @error('status') select-error @enderror" required>
                            <option value="">Select Status</option>
                            <option value="draft" {{ old('status', $invoice->status) == 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="sent" {{ old('status', $invoice->status) == 'sent' ? 'selected' : '' }}>Sent</option>
                            <option value="paid" {{ old('status', $invoice->status) == 'paid' ? 'selected' : '' }}>Paid</option>
                            <option value="overdue" {{ old('status', $invoice->status) == 'overdue' ? 'selected' : '' }}>Overdue</option>
                            <option value="cancelled" {{ old('status', $invoice->status) == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
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
                              placeholder="Describe the services performed...">{{ old('service_notes', $invoice->service_notes) }}</textarea>
                    @error('service_notes')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            
            <!-- Notification Settings -->
            <div class="mt-8 space-y-6">
                <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                    Notification Settings
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3">
                            <span class="text-base-content">Send notification to client</span>
                            <input type="checkbox" name="notification_sent" id="notification_sent" 
                                   value="1" {{ old('notification_sent', $invoice->notification_sent) ? 'checked' : '' }} 
                                   class="checkbox checkbox-primary">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="flex justify-end space-x-4 mt-8">
                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-outline">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Update Invoice
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rateInput = document.getElementById('rate_per_visit');
    const chemicalsCheckbox = document.getElementById('chemicals_included');
    const chemicalsCostInput = document.getElementById('chemicals_cost');
    const extrasInput = document.getElementById('extras_cost');
    
    const rateDisplay = document.getElementById('rate_display');
    const chemicalsDisplay = document.getElementById('chemicals_display');
    const chemicalsDisplayAmount = document.getElementById('chemicals_display_amount');
    const extrasDisplay = document.getElementById('extras_display');
    const totalDisplay = document.getElementById('total_display');
    
    function updateTotal() {
        const rate = parseFloat(rateInput.value) || 0;
        const chemicals = chemicalsCheckbox.checked ? 0 : (parseFloat(chemicalsCostInput.value) || 0);
        const extras = parseFloat(extrasInput.value) || 0;
        const total = rate + chemicals + extras;
        
        rateDisplay.textContent = `$${rate.toFixed(2)}`;
        chemicalsDisplayAmount.textContent = `$${chemicals.toFixed(2)}`;
        extrasDisplay.textContent = `$${extras.toFixed(2)}`;
        totalDisplay.textContent = `$${total.toFixed(2)}`;
        
        // Show/hide chemicals display based on checkbox
        if (chemicalsCheckbox.checked) {
            chemicalsDisplay.style.display = 'none';
        } else {
            chemicalsDisplay.style.display = 'flex';
        }
    }
    
    // Event listeners
    rateInput.addEventListener('input', updateTotal);
    chemicalsCostInput.addEventListener('input', updateTotal);
    extrasInput.addEventListener('input', updateTotal);
    
    // Handle chemicals checkbox change
    chemicalsCheckbox.addEventListener('change', function() {
        if (this.checked) {
            chemicalsCostInput.disabled = true;
            chemicalsCostInput.value = '0.00';
        } else {
            chemicalsCostInput.disabled = false;
        }
        updateTotal();
    });
    
    // Initialize total calculation and chemicals input state
    updateTotal();
    if (chemicalsCheckbox.checked) {
        chemicalsCostInput.disabled = true;
        chemicalsCostInput.value = '0.00';
    }
    
    // Client search functionality
    const clientSearch = document.getElementById('client_search');
    const clientIdInput = document.getElementById('client_id');
    const clientSuggestions = document.getElementById('client_suggestions');
    
    clientSearch.addEventListener('input', function() {
        const query = this.value.trim();
        if (query.length < 2) {
            clientSuggestions.style.display = 'none';
            return;
        }
        
        fetch(`/api/clients/search?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                clientSuggestions.innerHTML = '';
                if (data.length === 0) {
                    clientSuggestions.innerHTML = '<div class="p-3 text-base-content/70">No clients found</div>';
                    clientIdInput.value = '';
                } else {
                    data.forEach(client => {
                        const div = document.createElement('div');
                        div.className = 'p-3 hover:bg-base-200 cursor-pointer border-b border-base-300 last:border-b-0';
                        div.textContent = `${client.full_name} - ${client.email}`;
                        div.addEventListener('mousedown', function() {
                            clientSearch.value = client.full_name;
                            clientIdInput.value = client.id;
                            clientSuggestions.style.display = 'none';
                            clientSearch.setSelectionRange(client.full_name.length, client.full_name.length);
                        });
                        clientSuggestions.appendChild(div);
                    });
                }
                clientSuggestions.style.display = 'block';
            })
            .catch(error => {
                console.error('Error fetching clients:', error);
            });
    });
    
    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!clientSearch.contains(e.target) && !clientSuggestions.contains(e.target)) {
            clientSuggestions.style.display = 'none';
        }
    });
});
</script>
@endsection 