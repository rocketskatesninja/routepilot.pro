@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Add New Location</h1>
            <p class="text-base-content/70 mt-2">Create a new pool service location</p>
        </div>
        <a href="{{ route('locations.index') }}" class="btn btn-outline">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Locations
        </a>
    </div>

    <!-- Location Form -->
    <div class="card bg-base-100 shadow-xl border border-base-300">
        <form action="{{ route('locations.store') }}" method="POST" enctype="multipart/form-data" class="card-body p-6">
            @csrf
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Basic Information -->
                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                        Basic Information
                    </h3>
                    
                    <div>
                        <label for="client_search" class="block text-sm font-medium text-base-content mb-2">
                            Client
                        </label>
                        <input type="text" name="client_search" id="client_search" 
                               value="{{ old('client_search', $selectedClientName ?? '') }}" 
                               class="input input-bordered w-full @error('client_id') input-error @enderror" 
                               placeholder="Start typing client name..."
                               autocomplete="off" autocorrect="off" spellcheck="false">
                        <input type="hidden" name="client_id" id="client_id" value="{{ old('client_id', $selectedClientId ?? '') }}">
                        <div id="client_suggestions" class="hidden absolute z-50 min-w-full max-w-md w-auto left-0 bg-base-100 border border-base-300 rounded-lg shadow-lg max-h-60 overflow-y-auto"></div>
                        @error('client_id')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="nickname" class="block text-sm font-medium text-base-content mb-2">
                            Location Nickname
                        </label>
                        <input type="text" name="nickname" id="nickname" value="{{ old('nickname') }}" 
                               class="input input-bordered w-full @error('nickname') input-error @enderror" 
                               placeholder="e.g., Main Pool, Backyard Pool">
                        @error('nickname')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="street_address" class="block text-sm font-medium text-base-content mb-2">
                            Street Address <span class="text-error">*</span>
                        </label>
                        <input type="text" name="street_address" id="street_address" value="{{ old('street_address') }}" 
                               class="input input-bordered w-full @error('street_address') input-error @enderror" required>
                        @error('street_address')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="street_address_2" class="block text-sm font-medium text-base-content mb-2">
                            Street Address 2
                        </label>
                        <input type="text" name="street_address_2" id="street_address_2" value="{{ old('street_address_2') }}" 
                               class="input input-bordered w-full @error('street_address_2') input-error @enderror">
                        @error('street_address_2')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="city" class="block text-sm font-medium text-base-content mb-2">
                                City <span class="text-error">*</span>
                            </label>
                            <input type="text" name="city" id="city" value="{{ old('city') }}" 
                                   class="input input-bordered w-full @error('city') input-error @enderror" required>
                            @error('city')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="state" class="block text-sm font-medium text-base-content mb-2">
                                State <span class="text-error">*</span>
                            </label>
                            <input type="text" name="state" id="state" value="{{ old('state') }}" 
                                   class="input input-bordered w-full @error('state') input-error @enderror" maxlength="2" required>
                            @error('state')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="zip_code" class="block text-sm font-medium text-base-content mb-2">
                                ZIP Code <span class="text-error">*</span>
                            </label>
                            <input type="text" name="zip_code" id="zip_code" value="{{ old('zip_code') }}" 
                                   class="input input-bordered w-full @error('zip_code') input-error @enderror" required>
                            @error('zip_code')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="photos" class="block text-sm font-medium text-base-content mb-2">
                            Location Photos
                        </label>
                        <input type="file" name="photos[]" id="photos" 
                               class="file-input file-input-bordered w-full @error('photos') file-input-error @enderror"
                               accept="image/*" multiple>
                        <p class="text-sm text-base-content/70 mt-1">Select one or more images (JPEG, PNG, JPG, GIF up to 2MB each)</p>
                        @error('photos')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Pool Details and Requested Services (right column) -->
                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                        Pool Details
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="access" class="block text-sm font-medium text-base-content mb-2">
                                Access Type
                            </label>
                            <select name="access" id="access" class="select select-bordered w-full @error('access') select-error @enderror">
                                <option value="">Select Access Type</option>
                                <option value="residential" {{ old('access') == 'residential' ? 'selected' : '' }}>Residential</option>
                                <option value="commercial" {{ old('access') == 'commercial' ? 'selected' : '' }}>Commercial</option>
                            </select>
                            @error('access')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="pool_type" class="block text-sm font-medium text-base-content mb-2">
                                Pool Type
                            </label>
                            <select name="pool_type" id="pool_type" class="select select-bordered w-full @error('pool_type') select-error @enderror">
                                <option value="">Select Pool Type</option>
                                <option value="fiberglass" {{ old('pool_type') == 'fiberglass' ? 'selected' : '' }}>Fiberglass</option>
                                <option value="vinyl_liner" {{ old('pool_type') == 'vinyl_liner' ? 'selected' : '' }}>Vinyl Liner</option>
                                <option value="concrete" {{ old('pool_type') == 'concrete' ? 'selected' : '' }}>Concrete</option>
                                <option value="gunite" {{ old('pool_type') == 'gunite' ? 'selected' : '' }}>Gunite</option>
                            </select>
                            @error('pool_type')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="water_type" class="block text-sm font-medium text-base-content mb-2">
                                Water Type
                            </label>
                            <select name="water_type" id="water_type" class="select select-bordered w-full @error('water_type') select-error @enderror">
                                <option value="">Select Water Type</option>
                                <option value="chlorine" {{ old('water_type') == 'chlorine' ? 'selected' : '' }}>Chlorine</option>
                                <option value="salt" {{ old('water_type') == 'salt' ? 'selected' : '' }}>Salt</option>
                            </select>
                            @error('water_type')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="filter_type" class="block text-sm font-medium text-base-content mb-2">
                                Filter Type
                            </label>
                            <select name="filter_type" id="filter_type" class="select select-bordered w-full @error('filter_type') select-error @enderror">
                                <option value="">Select Filter Type</option>
                                <option value="sand" {{ old('filter_type') == 'sand' ? 'selected' : '' }}>Sand</option>
                                <option value="cartridge" {{ old('filter_type') == 'cartridge' ? 'selected' : '' }}>Cartridge</option>
                                <option value="de" {{ old('filter_type') == 'de' ? 'selected' : '' }}>DE (Diatomaceous Earth)</option>
                                <option value="other" {{ old('filter_type') == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                            @error('filter_type')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="setting" class="block text-sm font-medium text-base-content mb-2">
                                Setting
                            </label>
                            <select name="setting" id="setting" class="select select-bordered w-full @error('setting') select-error @enderror">
                                <option value="">Select Setting</option>
                                <option value="indoor" {{ old('setting') == 'indoor' ? 'selected' : '' }}>Indoor</option>
                                <option value="outdoor" {{ old('setting') == 'outdoor' ? 'selected' : '' }}>Outdoor</option>
                            </select>
                            @error('setting')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="installation" class="block text-sm font-medium text-base-content mb-2">
                                Installation Type
                            </label>
                            <select name="installation" id="installation" class="select select-bordered w-full @error('installation') select-error @enderror">
                                <option value="">Select Installation Type</option>
                                <option value="inground" {{ old('installation') == 'inground' ? 'selected' : '' }}>In-Ground</option>
                                <option value="above" {{ old('installation') == 'above' ? 'selected' : '' }}>Above Ground</option>
                            </select>
                            @error('installation')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="gallons" class="block text-sm font-medium text-base-content mb-2">
                                Pool Size (Gallons)
                            </label>
                            <input type="number" name="gallons" id="gallons" value="{{ old('gallons') }}" 
                                   class="input input-bordered w-full @error('gallons') input-error @enderror"
                                   placeholder="e.g., 15000">
                            @error('gallons')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Requested Services -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                            Requested Services
                        </h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            @foreach(['vacuum','brush','skim','clean_skimmer_basket','clean_pump_basket','clean_pool_deck'] as $task)
                                <div class="form-control">
                                    <label class="label cursor-pointer">
                                        <input type="checkbox" name="{{ $task }}" id="{{ $task }}" 
                                               class="checkbox checkbox-primary" 
                                               {{ old($task) ? 'checked' : '' }}>
                                        <span class="label-text ml-2 capitalize">{{ str_replace('_', ' ', $task) }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <h4 class="text-md font-semibold text-base-content mt-6">Maintenance Tasks</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            @foreach(['clean_filter_cartridge','backwash_sand_filter','adjust_water_level','adjust_auto_fill','adjust_pump_timer','adjust_heater','check_cover','check_lights','check_fountain','check_heater'] as $task)
                                <div class="form-control">
                                    <label class="label cursor-pointer">
                                        <input type="checkbox" name="{{ $task }}" id="{{ $task }}" 
                                               class="checkbox checkbox-primary" 
                                               {{ old($task) ? 'checked' : '' }}>
                                        <span class="label-text ml-2 capitalize">{{ str_replace('_', ' ', $task) }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

                <!-- Service Information -->
                <div class="mt-8 space-y-6">
                    <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                        Service Information
                    </h3>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div class="space-y-4">
                            <div>
                                <label for="service_frequency" class="block text-sm font-medium text-base-content mb-2">
                                    Service Frequency <span class="text-error">*</span>
                                </label>
                                <select name="service_frequency" id="service_frequency" class="select select-bordered w-full @error('service_frequency') select-error @enderror" required>
                                    <option value="">Select Frequency</option>
                                    <option value="weekly" {{ old('service_frequency') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                    <option value="bi-weekly" {{ old('service_frequency') == 'bi-weekly' ? 'selected' : '' }}>Bi-Weekly</option>
                                    <option value="monthly" {{ old('service_frequency') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                    <option value="as-needed" {{ old('service_frequency') == 'as-needed' ? 'selected' : '' }}>As Needed</option>
                                </select>
                                @error('service_frequency')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="service_day_1" class="block text-sm font-medium text-base-content mb-2">
                                    Primary Service Day
                                </label>
                                <select name="service_day_1" id="service_day_1" class="select select-bordered w-full @error('service_day_1') select-error @enderror">
                                    <option value="">Select Day</option>
                                    <option value="monday" {{ old('service_day_1') == 'monday' ? 'selected' : '' }}>Monday</option>
                                    <option value="tuesday" {{ old('service_day_1') == 'tuesday' ? 'selected' : '' }}>Tuesday</option>
                                    <option value="wednesday" {{ old('service_day_1') == 'wednesday' ? 'selected' : '' }}>Wednesday</option>
                                    <option value="thursday" {{ old('service_day_1') == 'thursday' ? 'selected' : '' }}>Thursday</option>
                                    <option value="friday" {{ old('service_day_1') == 'friday' ? 'selected' : '' }}>Friday</option>
                                    <option value="saturday" {{ old('service_day_1') == 'saturday' ? 'selected' : '' }}>Saturday</option>
                                    <option value="sunday" {{ old('service_day_1') == 'sunday' ? 'selected' : '' }}>Sunday</option>
                                </select>
                                @error('service_day_1')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="service_day_2" class="block text-sm font-medium text-base-content mb-2">
                                    Secondary Service Day
                                </label>
                                <select name="service_day_2" id="service_day_2" class="select select-bordered w-full @error('service_day_2') select-error @enderror">
                                    <option value="">Select Day (Optional)</option>
                                    <option value="monday" {{ old('service_day_2') == 'monday' ? 'selected' : '' }}>Monday</option>
                                    <option value="tuesday" {{ old('service_day_2') == 'tuesday' ? 'selected' : '' }}>Tuesday</option>
                                    <option value="wednesday" {{ old('service_day_2') == 'wednesday' ? 'selected' : '' }}>Wednesday</option>
                                    <option value="thursday" {{ old('service_day_2') == 'thursday' ? 'selected' : '' }}>Thursday</option>
                                    <option value="friday" {{ old('service_day_2') == 'friday' ? 'selected' : '' }}>Friday</option>
                                    <option value="saturday" {{ old('service_day_2') == 'saturday' ? 'selected' : '' }}>Saturday</option>
                                    <option value="sunday" {{ old('service_day_2') == 'sunday' ? 'selected' : '' }}>Sunday</option>
                                </select>
                                @error('service_day_2')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-end space-x-4">
                                <div class="flex-1">
                                    <label for="rate_per_visit" class="block text-sm font-medium text-base-content mb-2">
                                        Rate per Visit
                                    </label>
                                    <input type="number" name="rate_per_visit" id="rate_per_visit" value="{{ old('rate_per_visit') }}" 
                                           class="input input-bordered w-full @error('rate_per_visit') input-error @enderror"
                                           placeholder="e.g., 75.00" step="0.01" min="0">
                                    @error('rate_per_visit')
                                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="form-control mt-6">
                                    <label class="label cursor-pointer">
                                        <span class="label-text">Chemicals Included</span>
                                        <input type="checkbox" name="chemicals_included" value="1" 
                                               class="checkbox checkbox-primary" {{ old('chemicals_included', true) ? 'checked' : '' }}>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label for="assigned_technician_id" class="block text-sm font-medium text-base-content mb-2">
                                    Assigned Technician
                                </label>
                                <select name="assigned_technician_id" id="assigned_technician_id" class="select select-bordered w-full @error('assigned_technician_id') select-error @enderror">
                                    <option value="">Select Technician</option>
                                    @foreach($technicians as $technician)
                                        <option value="{{ $technician->id }}" {{ old('assigned_technician_id') == $technician->id ? 'selected' : '' }}>
                                            {{ $technician->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('assigned_technician_id')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="status" class="block text-sm font-medium text-base-content mb-2">
                                    Status
                                </label>
                                <select name="status" id="status" class="select select-bordered w-full @error('status') select-error @enderror">
                                    <option value="">Select Status</option>
                                    <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                                @error('status')
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
                    
                    <div>
                        <label for="notes" class="block text-sm font-medium text-base-content mb-2">
                            Additional Notes
                        </label>
                        <textarea name="notes" id="notes" rows="4" 
                                  class="textarea textarea-bordered w-full @error('notes') textarea-error @enderror"
                                  placeholder="Any additional notes about this location...">{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Services -->
                <div class="mt-8 space-y-6">
                    <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                        Services
                    </h3>
                    
                    <!-- Cleaning Tasks -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-base-content">Cleaning Tasks</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            @foreach(['vacuumed'=>'Vacuum','brushed'=>'Brush','skimmed'=>'Skim','cleaned_skimmer_basket'=>'Clean Skimmer Basket','cleaned_pump_basket'=>'Clean Pump Basket','cleaned_pool_deck'=>'Clean Pool Deck'] as $task => $label)
                                <div class="form-control">
                                    <label class="label cursor-pointer">
                                        <input type="checkbox" name="{{ $task }}" id="{{ $task }}" 
                                               class="checkbox checkbox-primary" 
                                               {{ old($task) ? 'checked' : '' }}>
                                        <span class="label-text ml-2">{{ $label }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Maintenance Tasks -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-base-content">Maintenance Tasks</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            @foreach(['cleaned_filter_cartridge'=>'Clean Filter Cartridge','backwashed_sand_filter'=>'Backwash Sand Filter','adjusted_water_level'=>'Adjust Water Level','adjusted_auto_fill'=>'Adjust Auto Fill','adjusted_pump_timer'=>'Adjust Pump Timer','adjusted_heater'=>'Adjust Heater','checked_cover'=>'Check Cover','checked_lights'=>'Check Lights','checked_fountain'=>'Check Fountain','checked_heater'=>'Check Heater'] as $task => $label)
                                <div class="form-control">
                                    <label class="label cursor-pointer">
                                        <input type="checkbox" name="{{ $task }}" id="{{ $task }}" 
                                               class="checkbox checkbox-primary" 
                                               {{ old($task) ? 'checked' : '' }}>
                                        <span class="label-text ml-2">{{ $label }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Other Services -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-base-content">Additional Services & Costs</h4>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <div>
                                <label for="other_services" class="block text-sm font-medium text-base-content mb-2">
                                    Other Services
                                </label>
                                <input type="text" name="other_services" id="other_services" 
                                       value="{{ old('other_services') }}" 
                                       placeholder="e.g. Filter Clean, Equipment Repair" 
                                       class="input input-bordered w-full @error('other_services') input-error @enderror">
                                @error('other_services')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label for="other_services_cost" class="block text-sm font-medium text-base-content mb-2">
                                    Other Services Cost
                                </label>
                                <input type="number" step="0.01" name="other_services_cost" id="other_services_cost" 
                                       value="{{ old('other_services_cost') }}" 
                                       class="input input-bordered w-full @error('other_services_cost') input-error @enderror">
                                @error('other_services_cost')
                                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="mt-8 flex justify-end space-x-4">
                    <a href="{{ route('locations.index') }}" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Create Location
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let lastKey = '';
document.addEventListener('DOMContentLoaded', function() {
    const clientSearch = document.getElementById('client_search');
    const clientId = document.getElementById('client_id');
    const suggestions = document.getElementById('client_suggestions');
    let clientsList = [];
    let activeIndex = -1;
    let lastValue = '';

    clientSearch.addEventListener('keydown', function(e) {
        lastKey = e.key;
    });
    
    clientSearch.addEventListener('input', function(e) {
        const query = this.value.trim();
        lastValue = query;
        activeIndex = -1;
        if (query.length < 2) {
            suggestions.classList.add('hidden');
            return;
        }
        fetch(`/api/clients/search?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                clientsList = data;
                suggestions.innerHTML = '';
                if (data.length === 0) {
                    suggestions.classList.add('hidden');
                    return;
                }
                data.forEach((client, idx) => {
                    const div = document.createElement('div');
                    div.className = 'px-4 py-2 hover:bg-base-200 cursor-pointer';
                    div.textContent = `${client.full_name} (${client.email})`;
                    div.addEventListener('mousedown', function(e) {
                        e.preventDefault();
                        clientSearch.value = client.full_name;
                        clientId.value = client.id;
                        suggestions.classList.add('hidden');
                        clientSearch.setSelectionRange(client.full_name.length, client.full_name.length);
                    });
                    suggestions.appendChild(div);
                });
                // Inline autocomplete: only if lastKey is not Backspace/Delete/Arrow
                const top = data[0];
                if (
                    top &&
                    top.full_name.toLowerCase().startsWith(query.toLowerCase()) &&
                    top.full_name.length > query.length &&
                    lastKey.length === 1 &&
                    !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(lastKey)
                ) {
                    clientSearch.value = top.full_name;
                    clientSearch.setSelectionRange(query.length, top.full_name.length);
                    clientId.value = top.id;
                } else {
                    clientId.value = '';
                }
                suggestions.classList.remove('hidden');
            })
            .catch(error => {
                console.error('Error fetching clients:', error);
            });
    });
    
    // Accept autocomplete with Tab or Right Arrow
    clientSearch.addEventListener('keydown', function(e) {
        if ((e.key === 'Tab' || e.key === 'ArrowRight') && this.selectionEnd > this.value.length - 1) {
            // Accept the suggestion
            this.setSelectionRange(this.value.length, this.value.length);
            suggestions.classList.add('hidden');
            e.preventDefault();
        } else if (e.key === 'ArrowDown') {
            // Move down in suggestions
            const items = suggestions.querySelectorAll('div');
            if (items.length > 0) {
                activeIndex = (activeIndex + 1) % items.length;
                items.forEach((el, idx) => el.classList.toggle('bg-base-200', idx === activeIndex));
                if (activeIndex >= 0) {
                    clientSearch.value = items[activeIndex].textContent.split(' (')[0];
                    clientId.value = clientsList[activeIndex].id;
                }
            }
            e.preventDefault();
        } else if (e.key === 'ArrowUp') {
            // Move up in suggestions
            const items = suggestions.querySelectorAll('div');
            if (items.length > 0) {
                activeIndex = (activeIndex - 1 + items.length) % items.length;
                items.forEach((el, idx) => el.classList.toggle('bg-base-200', idx === activeIndex));
                if (activeIndex >= 0) {
                    clientSearch.value = items[activeIndex].textContent.split(' (')[0];
                    clientId.value = clientsList[activeIndex].id;
                }
            }
            e.preventDefault();
        } else if (e.key === 'Enter') {
            // Accept highlighted suggestion
            const items = suggestions.querySelectorAll('div');
            if (activeIndex >= 0 && items[activeIndex]) {
                clientSearch.value = items[activeIndex].textContent.split(' (')[0];
                clientId.value = clientsList[activeIndex].id;
                suggestions.classList.add('hidden');
                this.setSelectionRange(this.value.length, this.value.length);
                e.preventDefault();
            }
        } else if (e.key === 'Escape') {
            suggestions.classList.add('hidden');
        }
    });
    
    // Hide suggestions when clicking outside
    document.addEventListener('mousedown', function(e) {
        if (!clientSearch.contains(e.target) && !suggestions.contains(e.target)) {
            suggestions.classList.add('hidden');
        }
    });
});
</script>
@endsection 