@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Add New Client</h1>
            <p class="text-base-content/70 mt-2">Create a new client profile</p>
        </div>
        <a href="{{ route('clients.index') }}" class="btn btn-outline">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Clients
        </a>
    </div>

    <!-- Client Form -->
    <div class="card bg-base-100 shadow-xl border border-base-300">
        <form action="{{ route('clients.store') }}" method="POST" enctype="multipart/form-data" class="card-body p-6" onsubmit="console.log('Form submitted with files:', document.getElementById('profile_photo').files)">
            @csrf
            
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
                            <input type="text" name="first_name" id="first_name" value="{{ old('first_name') }}" 
                                   class="input input-bordered w-full @error('first_name') input-error @enderror" required>
                            @error('first_name')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-base-content mb-2">
                                Last Name <span class="text-error">*</span>
                            </label>
                            <input type="text" name="last_name" id="last_name" value="{{ old('last_name') }}" 
                                   class="input input-bordered w-full @error('last_name') input-error @enderror" required>
                            @error('last_name')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="email" class="block text-sm font-medium text-base-content mb-2">
                                Email <span class="text-error">*</span>
                            </label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" 
                                   class="input input-bordered w-full @error('email') input-error @enderror" required>
                            @error('email')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="phone" class="block text-sm font-medium text-base-content mb-2">
                                Phone Number
                            </label>
                            <input type="tel" name="phone" id="phone" value="{{ old('phone') }}" 
                                   class="input input-bordered w-full @error('phone') input-error @enderror">
                            @error('phone')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="profile_photo" class="block text-sm font-medium text-base-content mb-2">
                            Profile Photo
                        </label>
                        <input type="file" name="profile_photo" id="profile_photo" 
                               class="file-input file-input-bordered w-full @error('profile_photo') file-input-error @enderror"
                               accept="image/*" onchange="console.log('File selected:', this.files[0])">
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
                        <input type="text" name="street_address" id="street_address" value="{{ old('street_address') }}" 
                               class="input input-bordered w-full @error('street_address') input-error @enderror">
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
                                City
                            </label>
                            <input type="text" name="city" id="city" value="{{ old('city') }}" 
                                   class="input input-bordered w-full @error('city') input-error @enderror">
                            @error('city')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="state" class="block text-sm font-medium text-base-content mb-2">
                                State
                            </label>
                            <input type="text" name="state" id="state" value="{{ old('state') }}" 
                                   class="input input-bordered w-full @error('state') input-error @enderror" maxlength="2">
                            @error('state')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="zip_code" class="block text-sm font-medium text-base-content mb-2">
                                ZIP Code
                            </label>
                            <input type="text" name="zip_code" id="zip_code" value="{{ old('zip_code') }}" 
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
                                <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('status')
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
                                       class="checkbox checkbox-primary" {{ old('appointment_reminders', true) ? 'checked' : '' }}>
                            </div>
                            <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3">
                                <span class="text-base-content">Mailing List</span>
                                <input type="checkbox" name="mailing_list" value="1" 
                                       class="checkbox checkbox-primary" {{ old('mailing_list', true) ? 'checked' : '' }}>
                            </div>
                            <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3">
                                <span class="text-base-content">Monthly Billing</span>
                                <input type="checkbox" name="monthly_billing" value="1" 
                                       class="checkbox checkbox-primary" {{ old('monthly_billing', true) ? 'checked' : '' }}>
                            </div>
                            <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3">
                                <span class="text-base-content">Service Reports</span>
                                <div class="flex items-center space-x-4">
                                    <input type="checkbox" name="service_reports_enabled" id="service_reports_enabled" value="1" 
                                           class="checkbox checkbox-primary" {{ old('service_reports_enabled', true) ? 'checked' : '' }}>
                                    <select name="service_reports" id="service_reports" class="select select-bordered select-sm">
                                        <option value="full" {{ old('service_reports', 'full') == 'full' ? 'selected' : '' }}>Full Reports</option>
                                        <option value="invoice_only" {{ old('service_reports', 'full') == 'invoice_only' ? 'selected' : '' }}>Invoice Only</option>
                                        <option value="services_only" {{ old('service_reports', 'full') == 'services_only' ? 'selected' : '' }}>Services Only</option>
                                    </select>
                                </div>
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
                              placeholder="Any additional notes about this client...">{{ old('notes_by_admin') }}</textarea>
                    @error('notes_by_admin')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-between items-center">
                <div class="flex space-x-8">
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="create_first_location" class="checkbox checkbox-primary" checked>
                        <span class="ml-2">Use this address for the first location</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="send_welcome_email" class="checkbox checkbox-primary" checked>
                        <span class="ml-2">Send welcome email with login information</span>
                    </label>
                </div>
                <div class="flex space-x-4">
                    <a href="{{ route('clients.index') }}" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Create Client
                    </button>
                </div>
            </div>
            <script>
            // Service reports checkbox behavior
            document.addEventListener('DOMContentLoaded', function() {
                const serviceReportsCheckbox = document.getElementById('service_reports_enabled');
                const serviceReportsSelect = document.getElementById('service_reports');

                function toggleServiceReports() {
                    serviceReportsSelect.disabled = !serviceReportsCheckbox.checked;
                    if (!serviceReportsCheckbox.checked) {
                        serviceReportsSelect.value = '';
                    }
                }

                // Initial state
                toggleServiceReports();

                // Event listener
                serviceReportsCheckbox.addEventListener('change', toggleServiceReports);
            });
            </script>
        </form>
    </div>
</div>
@endsection 