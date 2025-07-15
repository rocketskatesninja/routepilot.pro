@extends('layouts.app')

@section('title', 'Create Invoice')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Create New Invoice</h1>
            <p class="text-base-content/70 mt-2">Generate a new invoice for pool service</p>
        </div>
        <a href="{{ route('invoices.index') }}" class="btn btn-ghost">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Invoices
        </a>
    </div>

    <div class="card bg-base-100 shadow-xl border border-base-300">
        <div class="card-body">
            <form action="{{ route('invoices.store') }}" method="POST" id="invoice-form">
                @csrf
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Service Information -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-base-content">Service Information</h3>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Client *</span>
                            </label>
                            <select name="client_id" id="client_id" 
                                    class="select select-bordered @error('client_id') select-error @enderror" 
                                    required>
                                <option value="">Select Client</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>
                                        {{ $client->full_name }} - {{ $client->email }}
                                    </option>
                                @endforeach
                            </select>
                            @error('client_id')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Location *</span>
                            </label>
                            <select name="location_id" id="location_id" 
                                    class="select select-bordered @error('location_id') select-error @enderror" 
                                    required>
                                <option value="">Select Location</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" {{ old('location_id') == $location->id ? 'selected' : '' }}>
                                        {{ $location->name }} - {{ $location->city }}, {{ $location->state }}
                                    </option>
                                @endforeach
                            </select>
                            @error('location_id')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Technician *</span>
                            </label>
                            <select name="technician_id" 
                                    class="select select-bordered @error('technician_id') select-error @enderror" 
                                    required>
                                <option value="">Select Technician</option>
                                @foreach($technicians as $technician)
                                    <option value="{{ $technician->id }}" {{ old('technician_id') == $technician->id ? 'selected' : '' }}>
                                        {{ $technician->full_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('technician_id')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Service Date *</span>
                                </label>
                                <input type="date" name="service_date" value="{{ old('service_date', date('Y-m-d')) }}" 
                                       class="input input-bordered @error('service_date') input-error @enderror" 
                                       required>
                                @error('service_date')
                                    <label class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>
                            
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Due Date *</span>
                                </label>
                                <input type="date" name="due_date" value="{{ old('due_date', date('Y-m-d', strtotime('+30 days'))) }}" 
                                       class="input input-bordered @error('due_date') input-error @enderror" 
                                       required>
                                @error('due_date')
                                    <label class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cost Information -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-base-content">Cost Information</h3>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Rate per Visit *</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="rate_per_visit" id="rate_per_visit" 
                                       value="{{ old('rate_per_visit', '75.00') }}" step="0.01" min="0" 
                                       class="input input-bordered @error('rate_per_visit') input-error @enderror" 
                                       required>
                            </div>
                            @error('rate_per_visit')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
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
                        
                        <div class="form-control" id="chemicals_cost_group" style="display: none;">
                            <label class="label">
                                <span class="label-text">Chemicals Cost</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="chemicals_cost" id="chemicals_cost" 
                                       value="{{ old('chemicals_cost', '25.00') }}" step="0.01" min="0" 
                                       class="input input-bordered @error('chemicals_cost') input-error @enderror">
                            </div>
                            @error('chemicals_cost')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Extras Cost</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="extras_cost" id="extras_cost" 
                                       value="{{ old('extras_cost', '0.00') }}" step="0.01" min="0" 
                                       class="input input-bordered @error('extras_cost') input-error @enderror">
                            </div>
                            @error('extras_cost')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
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
                <div class="divider"></div>
                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-base-content">Service Details</h3>
                    
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Service Type *</span>
                        </label>
                        <select name="service_type" class="select select-bordered @error('service_type') select-error @enderror" required>
                            <option value="">Select Service Type</option>
                            <option value="regular" {{ old('service_type') == 'regular' ? 'selected' : '' }}>Regular Service</option>
                            <option value="chemical" {{ old('service_type') == 'chemical' ? 'selected' : '' }}>Chemical Service</option>
                            <option value="repair" {{ old('service_type') == 'repair' ? 'selected' : '' }}>Repair Service</option>
                            <option value="cleaning" {{ old('service_type') == 'cleaning' ? 'selected' : '' }}>Deep Cleaning</option>
                            <option value="inspection" {{ old('service_type') == 'inspection' ? 'selected' : '' }}>Inspection</option>
                        </select>
                        @error('service_type')
                            <label class="label">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </label>
                        @enderror
                    </div>
                    
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Service Notes</span>
                        </label>
                        <textarea name="service_notes" rows="4" 
                                  class="textarea textarea-bordered @error('service_notes') textarea-error @enderror"
                                  placeholder="Describe the services performed...">{{ old('service_notes') }}</textarea>
                        @error('service_notes')
                            <label class="label">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </label>
                        @enderror
                    </div>
                    
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Status *</span>
                        </label>
                        <select name="status" class="select select-bordered @error('status') select-error @enderror" required>
                            <option value="">Select Status</option>
                            <option value="draft" {{ old('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="sent" {{ old('status') == 'sent' ? 'selected' : '' }}>Sent</option>
                            <option value="paid" {{ old('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                            <option value="overdue" {{ old('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                            <option value="cancelled" {{ old('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                        @error('status')
                            <label class="label">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </label>
                        @enderror
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="divider"></div>
                <div class="flex justify-end space-x-4">
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
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
});
</script>
@endsection 