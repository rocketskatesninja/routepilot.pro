@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Edit Client</h1>
            <p class="text-base-content/70 mt-2">Update client profile information</p>
        </div>
        <div class="flex space-x-2">
            <a href="{{ route('clients.show', $client) }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                View Client
            </a>
            <form action="{{ route('clients.destroy', $client) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this client? This action cannot be undone.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn bg-red-600 hover:bg-red-700 text-white border-red-600">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete Client
                </button>
            </form>
            <a href="{{ route('clients.index') }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Clients
            </a>
        </div>
    </div>

    <!-- Client Form -->
    <div class="card bg-base-100 shadow-xl border border-base-300">
        <form action="{{ route('clients.update', $client) }}" method="POST" enctype="multipart/form-data" class="card-body p-6">
            @csrf
            @method('PUT')
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Basic Information -->
                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                        Basic Information
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-base-content mb-2">
                                First Name <span class="text-error">*</span>
                            </label>
                            <input type="text" name="first_name" id="first_name" value="{{ old('first_name', $client->first_name) }}" 
                                   class="input input-bordered w-full @error('first_name') input-error @enderror" required>
                            @error('first_name')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="last_name" class="block text-sm font-medium text-base-content mb-2">
                                Last Name <span class="text-error">*</span>
                            </label>
                            <input type="text" name="last_name" id="last_name" value="{{ old('last_name', $client->last_name) }}" 
                                   class="input input-bordered w-full @error('last_name') input-error @enderror" required>
                            @error('last_name')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-base-content mb-2">
                            Email <span class="text-error">*</span>
                        </label>
                        <input type="email" name="email" id="email" value="{{ old('email', $client->email) }}" 
                               class="input input-bordered w-full @error('email') input-error @enderror" required>
                        @error('email')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-base-content mb-2">
                            Phone Number
                        </label>
                        <input type="tel" name="phone" id="phone" value="{{ old('phone', $client->phone) }}" 
                               class="input input-bordered w-full @error('phone') input-error @enderror">
                        @error('phone')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="role" class="block text-sm font-medium text-base-content mb-2">
                            Role <span class="text-error">*</span>
                        </label>
                        <select name="role" id="role" class="select select-bordered w-full @error('role') select-error @enderror" required>
                            <option value="">Select Role</option>
                            <option value="client" {{ old('role', $client->role) == 'client' ? 'selected' : '' }}>Client</option>
                            <option value="tech" {{ old('role', $client->role) == 'tech' ? 'selected' : '' }}>Technician</option>
                            <option value="admin" {{ old('role', $client->role) == 'admin' ? 'selected' : '' }}>Admin</option>
                        </select>
                        @error('role')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="profile_photo" class="block text-sm font-medium text-base-content mb-2">
                            Profile Photo
                        </label>
                        @if($client->profile_photo)
                            <div class="mb-2">
                                <img src="{{ Storage::url($client->profile_photo) }}" alt="Current profile photo" class="w-20 h-20 rounded-lg object-cover">
                            </div>
                        @endif
                        <input type="file" name="profile_photo" id="profile_photo" 
                               class="file-input file-input-bordered w-full @error('profile_photo') file-input-error @enderror"
                               accept="image/*">
                        @error('profile_photo')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Address Information -->
                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                        Address Information
                    </h3>
                    
                    <div>
                        <label for="street_address" class="block text-sm font-medium text-base-content mb-2">
                            Street Address
                        </label>
                        <input type="text" name="street_address" id="street_address" value="{{ old('street_address', $client->street_address) }}" 
                               class="input input-bordered w-full @error('street_address') input-error @enderror">
                        @error('street_address')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="street_address_2" class="block text-sm font-medium text-base-content mb-2">
                            Street Address 2
                        </label>
                        <input type="text" name="street_address_2" id="street_address_2" value="{{ old('street_address_2', $client->street_address_2) }}" 
                               class="input input-bordered w-full @error('street_address_2') input-error @enderror">
                        @error('street_address_2')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="city" class="block text-sm font-medium text-base-content mb-2">
                                City
                            </label>
                            <input type="text" name="city" id="city" value="{{ old('city', $client->city) }}" 
                                   class="input input-bordered w-full @error('city') input-error @enderror">
                            @error('city')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="state" class="block text-sm font-medium text-base-content mb-2">
                                State
                            </label>
                            <input type="text" name="state" id="state" value="{{ old('state', $client->state) }}" 
                                   class="input input-bordered w-full @error('state') input-error @enderror" maxlength="2">
                            @error('state')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="zip_code" class="block text-sm font-medium text-base-content mb-2">
                                ZIP Code
                            </label>
                            <input type="text" name="zip_code" id="zip_code" value="{{ old('zip_code', $client->zip_code) }}" 
                                   class="input input-bordered w-full @error('zip_code') input-error @enderror">
                            @error('zip_code')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preferences and Settings -->
            <div class="mt-8 space-y-6">
                <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                    Preferences & Settings
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="space-y-4">
                        <div>
                            <label for="status" class="block text-sm font-medium text-base-content mb-2">
                                Status <span class="text-error">*</span>
                            </label>
                            <select name="status" id="status" class="select select-bordered w-full @error('status') select-error @enderror" required>
                                <option value="">Select Status</option>
                                <option value="active" {{ old('status', $client->status) == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status', $client->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('status')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="service_reports" class="block text-sm font-medium text-base-content mb-2">
                                Service Reports <span class="text-error">*</span>
                            </label>
                            <select name="service_reports" id="service_reports" class="select select-bordered w-full @error('service_reports') select-error @enderror" required>
                                <option value="">Select Report Type</option>
                                <option value="full" {{ old('service_reports', $client->service_reports) == 'full' ? 'selected' : '' }}>Full Reports</option>
                                <option value="invoice_only" {{ old('service_reports', $client->service_reports) == 'invoice_only' ? 'selected' : '' }}>Invoice Only</option>
                                <option value="none" {{ old('service_reports', $client->service_reports) == 'none' ? 'selected' : '' }}>No Reports</option>
                            </select>
                            @error('service_reports')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Notification Preferences -->
                    <div class="space-y-6">
                        <h4 class="text-md font-semibold text-base-content">Notification Preferences</h4>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3">
                                <span class="text-base-content">Appointment Reminders</span>
                                <input type="checkbox" name="appointment_reminders" value="1" 
                                       class="checkbox checkbox-primary" {{ old('appointment_reminders', $client->appointment_reminders) ? 'checked' : '' }}>
                            </div>

                            <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3">
                                <span class="text-base-content">Mailing List</span>
                                <input type="checkbox" name="mailing_list" value="1" 
                                       class="checkbox checkbox-primary" {{ old('mailing_list', $client->mailing_list) ? 'checked' : '' }}>
                            </div>

                            <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3">
                                <span class="text-base-content">Monthly Billing</span>
                                <input type="checkbox" name="monthly_billing" value="1" 
                                       class="checkbox checkbox-primary" {{ old('monthly_billing', $client->monthly_billing) ? 'checked' : '' }}>
                            </div>

                            <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3">
                                <span class="text-base-content">Active Account</span>
                                <input type="checkbox" name="is_active" value="1" 
                                       class="checkbox checkbox-primary" {{ old('is_active', $client->is_active) ? 'checked' : '' }}>
                            </div>
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
                    <label for="notes_by_admin" class="block text-sm font-medium text-base-content mb-2">
                        Additional Notes
                    </label>
                    <textarea name="notes_by_admin" id="notes_by_admin" rows="4"
                              class="textarea textarea-bordered w-full @error('notes_by_admin') textarea-error @enderror"
                              placeholder="Any additional notes about this client...">{{ old('notes_by_admin', $client->notes_by_admin) }}</textarea>
                    @error('notes_by_admin')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-base-content mb-2">Client Notes</label>
                    <textarea class="textarea textarea-bordered w-full" rows="4" readonly>{{ $client->notes_by_client }}</textarea>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-4">
                <a href="{{ route('clients.index') }}" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Update Client
                </button>
            </div>
        </form>
    </div>

    <!-- Hidden delete form -->
    <form id="deleteForm" action="{{ route('clients.destroy', $client) }}" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>

    <script>
        function deleteClient() {
            if (confirm('Are you sure you want to delete this client? This action cannot be undone.')) {
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</div>
@endsection 