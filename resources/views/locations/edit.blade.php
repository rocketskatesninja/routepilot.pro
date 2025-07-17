@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Edit Location</h1>
            <p class="text-base-content/70 mt-2">Update pool service location information</p>
        </div>
        <div class="flex space-x-2">
            <a href="{{ route('locations.show', $location) }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                View Location
            </a>
            <form action="{{ route('locations.destroy', $location) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this location? This action cannot be undone.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn bg-red-600 hover:bg-red-700 text-white border-red-600">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete Location
                </button>
            </form>
            <a href="{{ route('locations.index') }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Locations
            </a>
        </div>
    </div>

    <!-- Location Form -->
    <div class="card bg-base-100 shadow-xl border border-base-300">
        <form action="{{ route('locations.update', $location) }}" method="POST" enctype="multipart/form-data" class="card-body p-6">
            @csrf
            @method('PUT')
            
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
                        <input type="text" name="client_search" id="client_search" 
                               value="{{ old('client_search', $location->client->full_name ?? '') }}" 
                               class="input input-bordered w-full @error('client_id') input-error @enderror" 
                               placeholder="Start typing client name..." required>
                        <input type="hidden" name="client_id" id="client_id" value="{{ old('client_id', $location->client_id) }}">
                        <div id="client_suggestions" class="hidden absolute z-50 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg max-h-60 overflow-y-auto"></div>
                        @error('client_id')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="nickname" class="block text-sm font-medium text-base-content mb-2">
                            Location Nickname
                        </label>
                        <input type="text" name="nickname" id="nickname" value="{{ old('nickname', $location->nickname) }}" 
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
                        <input type="text" name="street_address" id="street_address" value="{{ old('street_address', $location->street_address) }}" 
                               class="input input-bordered w-full @error('street_address') input-error @enderror" required>
                        @error('street_address')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="street_address_2" class="block text-sm font-medium text-base-content mb-2">
                            Street Address 2
                        </label>
                        <input type="text" name="street_address_2" id="street_address_2" value="{{ old('street_address_2', $location->street_address_2) }}" 
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
                            <input type="text" name="city" id="city" value="{{ old('city', $location->city) }}" 
                                   class="input input-bordered w-full @error('city') input-error @enderror" required>
                            @error('city')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="state" class="block text-sm font-medium text-base-content mb-2">
                                State <span class="text-error">*</span>
                            </label>
                            <input type="text" name="state" id="state" value="{{ old('state', $location->state) }}" 
                                   class="input input-bordered w-full @error('state') input-error @enderror" maxlength="2" required>
                            @error('state')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="zip_code" class="block text-sm font-medium text-base-content mb-2">
                                ZIP Code <span class="text-error">*</span>
                            </label>
                            <input type="text" name="zip_code" id="zip_code" value="{{ old('zip_code', $location->zip_code) }}" 
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
                        @if($location->photos)
                            <div class="mb-2">
                                <p class="text-sm text-base-content/70">Current photos:</p>
                                <div class="flex flex-wrap gap-2 mt-2">
                                    @foreach($location->photos as $photo)
                                        <img src="{{ Storage::url($photo) }}" alt="Location photo" class="w-16 h-16 rounded object-cover">
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <input type="file" name="photos[]" id="photos" 
                               class="file-input file-input-bordered w-full @error('photos') file-input-error @enderror"
                               accept="image/*" multiple>
                        <p class="text-sm text-base-content/70 mt-1">Select one or more images (JPEG, PNG, JPG, GIF up to 2MB each). Leave empty to keep existing photos.</p>
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
                                Access Type <span class="text-error">*</span>
                            </label>
                            <select name="access" id="access" class="select select-bordered w-full @error('access') select-error @enderror" required>
                                <option value="">Select Access Type</option>
                                <option value="residential" {{ old('access', $location->access) == 'residential' ? 'selected' : '' }}>Residential</option>
                                <option value="commercial" {{ old('access', $location->access) == 'commercial' ? 'selected' : '' }}>Commercial</option>
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
                                <option value="fiberglass" {{ old('pool_type', $location->pool_type) == 'fiberglass' ? 'selected' : '' }}>Fiberglass</option>
                                <option value="vinyl_liner" {{ old('pool_type', $location->pool_type) == 'vinyl_liner' ? 'selected' : '' }}>Vinyl Liner</option>
                                <option value="concrete" {{ old('pool_type', $location->pool_type) == 'concrete' ? 'selected' : '' }}>Concrete</option>
                                <option value="gunite" {{ old('pool_type', $location->pool_type) == 'gunite' ? 'selected' : '' }}>Gunite</option>
                            </select>
                            @error('pool_type')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="water_type" class="block text-sm font-medium text-base-content mb-2">
                                Water Type <span class="text-error">*</span>
                            </label>
                            <select name="water_type" id="water_type" class="select select-bordered w-full @error('water_type') select-error @enderror" required>
                                <option value="">Select Water Type</option>
                                <option value="chlorine" {{ old('water_type', $location->water_type) == 'chlorine' ? 'selected' : '' }}>Chlorine</option>
                                <option value="salt" {{ old('water_type', $location->water_type) == 'salt' ? 'selected' : '' }}>Salt</option>
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
                                <option value="sand" {{ old('filter_type', $location->filter_type) == 'sand' ? 'selected' : '' }}>Sand</option>
                                <option value="cartridge" {{ old('filter_type', $location->filter_type) == 'cartridge' ? 'selected' : '' }}>Cartridge</option>
                                <option value="de" {{ old('filter_type', $location->filter_type) == 'de' ? 'selected' : '' }}>DE (Diatomaceous Earth)</option>
                                <option value="other" {{ old('filter_type', $location->filter_type) == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                            @error('filter_type')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="setting" class="block text-sm font-medium text-base-content mb-2">
                                Setting <span class="text-error">*</span>
                            </label>
                            <select name="setting" id="setting" class="select select-bordered w-full @error('setting') select-error @enderror" required>
                                <option value="">Select Setting</option>
                                <option value="indoor" {{ old('setting', $location->setting) == 'indoor' ? 'selected' : '' }}>Indoor</option>
                                <option value="outdoor" {{ old('setting', $location->setting) == 'outdoor' ? 'selected' : '' }}>Outdoor</option>
                            </select>
                            @error('setting')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="installation" class="block text-sm font-medium text-base-content mb-2">
                                Installation Type <span class="text-error">*</span>
                            </label>
                            <select name="installation" id="installation" class="select select-bordered w-full @error('installation') select-error @enderror" required>
                                <option value="">Select Installation Type</option>
                                <option value="inground" {{ old('installation', $location->installation) == 'inground' ? 'selected' : '' }}>In-Ground</option>
                                <option value="above" {{ old('installation', $location->installation) == 'above' ? 'selected' : '' }}>Above Ground</option>
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
                            <input type="number" name="gallons" id="gallons" value="{{ old('gallons', $location->gallons) }}" 
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
                                               {{ old($task, $location->$task ?? false) ? 'checked' : '' }}>
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
                                               {{ old($task, $location->$task ?? false) ? 'checked' : '' }}>
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
                                <option value="weekly" {{ old('service_frequency', $location->service_frequency) == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                <option value="bi-weekly" {{ old('service_frequency', $location->service_frequency) == 'bi-weekly' ? 'selected' : '' }}>Bi-Weekly</option>
                                <option value="monthly" {{ old('service_frequency', $location->service_frequency) == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                <option value="as-needed" {{ old('service_frequency', $location->service_frequency) == 'as-needed' ? 'selected' : '' }}>As Needed</option>
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
                                <option value="monday" {{ old('service_day_1', $location->service_day_1) == 'monday' ? 'selected' : '' }}>Monday</option>
                                <option value="tuesday" {{ old('service_day_1', $location->service_day_1) == 'tuesday' ? 'selected' : '' }}>Tuesday</option>
                                <option value="wednesday" {{ old('service_day_1', $location->service_day_1) == 'wednesday' ? 'selected' : '' }}>Wednesday</option>
                                <option value="thursday" {{ old('service_day_1', $location->service_day_1) == 'thursday' ? 'selected' : '' }}>Thursday</option>
                                <option value="friday" {{ old('service_day_1', $location->service_day_1) == 'friday' ? 'selected' : '' }}>Friday</option>
                                <option value="saturday" {{ old('service_day_1', $location->service_day_1) == 'saturday' ? 'selected' : '' }}>Saturday</option>
                                <option value="sunday" {{ old('service_day_1', $location->service_day_1) == 'sunday' ? 'selected' : '' }}>Sunday</option>
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
                                <option value="monday" {{ old('service_day_2', $location->service_day_2) == 'monday' ? 'selected' : '' }}>Monday</option>
                                <option value="tuesday" {{ old('service_day_2', $location->service_day_2) == 'tuesday' ? 'selected' : '' }}>Tuesday</option>
                                <option value="wednesday" {{ old('service_day_2', $location->service_day_2) == 'wednesday' ? 'selected' : '' }}>Wednesday</option>
                                <option value="thursday" {{ old('service_day_2', $location->service_day_2) == 'thursday' ? 'selected' : '' }}>Thursday</option>
                                <option value="friday" {{ old('service_day_2', $location->service_day_2) == 'friday' ? 'selected' : '' }}>Friday</option>
                                <option value="saturday" {{ old('service_day_2', $location->service_day_2) == 'saturday' ? 'selected' : '' }}>Saturday</option>
                                <option value="sunday" {{ old('service_day_2', $location->service_day_2) == 'sunday' ? 'selected' : '' }}>Sunday</option>
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
                                <input type="number" name="rate_per_visit" id="rate_per_visit" value="{{ old('rate_per_visit', $location->rate_per_visit) }}" 
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
                                           class="checkbox checkbox-primary" {{ old('chemicals_included', $location->chemicals_included) ? 'checked' : '' }}>
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
                                    <option value="{{ $technician->id }}" {{ old('assigned_technician_id', $location->assigned_technician_id) == $technician->id ? 'selected' : '' }}>
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
                                Status <span class="text-error">*</span>
                            </label>
                            <select name="status" id="status" class="select select-bordered w-full @error('status') select-error @enderror" required>
                                <option value="">Select Status</option>
                                <option value="active" {{ old('status', $location->status) == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status', $location->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
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
                              placeholder="Any additional notes about this location...">{{ old('notes', $location->notes) }}</textarea>
                    @error('notes')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-4">
                <a href="{{ route('locations.index') }}" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Update Location
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const clientSearch = document.getElementById('client_search');
    const clientId = document.getElementById('client_id');
    const suggestions = document.getElementById('client_suggestions');
    
    clientSearch.addEventListener('input', function() {
        const query = this.value.trim();
        
        if (query.length < 2) {
            suggestions.classList.add('hidden');
            return;
        }
        
        fetch(`/api/clients/search?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                suggestions.innerHTML = '';
                
                if (data.length === 0) {
                    suggestions.classList.add('hidden');
                    return;
                }
                
                data.forEach(client => {
                    const div = document.createElement('div');
                    div.className = 'px-4 py-2 hover:bg-base-200 cursor-pointer';
                    div.textContent = `${client.full_name} (${client.email})`;
                    div.addEventListener('click', function() {
                        clientSearch.value = client.full_name;
                        clientId.value = client.id;
                        suggestions.classList.add('hidden');
                    });
                    suggestions.appendChild(div);
                });
                
                suggestions.classList.remove('hidden');
            })
            .catch(error => {
                console.error('Error fetching clients:', error);
            });
    });
    
    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!clientSearch.contains(e.target) && !suggestions.contains(e.target)) {
            suggestions.classList.add('hidden');
        }
    });
    
    // Handle keyboard navigation
    clientSearch.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            suggestions.classList.add('hidden');
        }
    });
});
</script>
@endsection 