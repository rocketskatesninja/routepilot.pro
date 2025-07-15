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
                        <label for="client_id" class="block text-sm font-medium text-base-content mb-2">
                            Client <span class="text-error">*</span>
                        </label>
                        <select name="client_id" id="client_id" class="select select-bordered w-full @error('client_id') select-error @enderror" required>
                            <option value="">Select Client</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" {{ old('client_id', $selectedClientId) == $client->id ? 'selected' : '' }}>
                                    {{ $client->full_name }} ({{ $client->email }})
                                </option>
                            @endforeach
                        </select>
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
                        @error('photos')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Pool Details -->
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
                                Water Type <span class="text-error">*</span>
                            </label>
                            <select name="water_type" id="water_type" class="select select-bordered w-full @error('water_type') select-error @enderror" required>
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
                            <input type="text" name="filter_type" id="filter_type" value="{{ old('filter_type') }}" 
                                   class="input input-bordered w-full @error('filter_type') input-error @enderror"
                                   placeholder="e.g., Sand, Cartridge, DE">
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
                                <option value="residential" {{ old('setting') == 'residential' ? 'selected' : '' }}>Residential</option>
                                <option value="commercial" {{ old('setting') == 'commercial' ? 'selected' : '' }}>Commercial</option>
                                <option value="public" {{ old('setting') == 'public' ? 'selected' : '' }}>Public</option>
                            </select>
                            @error('setting')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="pool_size" class="block text-sm font-medium text-base-content mb-2">
                                Pool Size (Gallons)
                            </label>
                            <input type="number" name="pool_size" id="pool_size" value="{{ old('pool_size') }}" 
                                   class="input input-bordered w-full @error('pool_size') input-error @enderror"
                                   placeholder="e.g., 15000">
                            @error('pool_size')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
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
                                Status <span class="text-error">*</span>
                            </label>
                            <select name="status" id="status" class="select select-bordered w-full @error('status') select-error @enderror" required>
                                <option value="">Select Status</option>
                                <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                <option value="pending" {{ old('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            </select>
                            @error('status')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="form-control">
                            <label class="label cursor-pointer">
                                <span class="label-text">Active Location</span>
                                <input type="checkbox" name="is_active" value="1" 
                                       class="checkbox checkbox-primary" {{ old('is_active', true) ? 'checked' : '' }}>
                            </label>
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
        </form>
    </div>
</div>
@endsection 