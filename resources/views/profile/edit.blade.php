@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Profile</h1>
            <p class="text-base-content/70 mt-2">Manage your personal information and preferences</p>
        </div>
    </div>



    <div class="card bg-base-100 shadow-xl border border-base-300">
        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="card-body p-6" onsubmit="logFormData(event)">
            @csrf
            @method('PATCH')
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Left Column: Personal Information -->
                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">Personal Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-2">First Name <span class="text-error">*</span></label>
                            <input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}" class="input input-bordered w-full" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-2">Last Name <span class="text-error">*</span></label>
                            <input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}" class="input input-bordered w-full" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Street Address</label>
                        <input type="text" name="street_address" value="{{ old('street_address', $user->street_address) }}" class="input input-bordered w-full">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Street Address 2</label>
                        <input type="text" name="street_address_2" value="{{ old('street_address_2', $user->street_address_2) }}" class="input input-bordered w-full">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-2">City</label>
                            <input type="text" name="city" value="{{ old('city', $user->city) }}" class="input input-bordered w-full">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-2">State</label>
                            <input type="text" name="state" value="GA" class="input input-bordered w-full" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-2">ZIP Code</label>
                            <input type="text" name="zip_code" value="{{ old('zip_code', $user->zip_code) }}" class="input input-bordered w-full">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-2">Email Address</label>
                            <input type="email" name="email" value="{{ $user->email }}" class="input input-bordered w-full" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-2">Phone Number</label>
                            <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" class="input input-bordered w-full">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Profile Photo</label>
                        @if($user->profile_photo)
                            <div class="mb-2 relative inline-block">
                                <img src="{{ asset(Storage::url($user->profile_photo)) }}" alt="Current profile photo" class="w-20 h-20 rounded-lg object-cover">
                                <button type="button" class="absolute top-0 right-0 z-10 bg-red-600 text-white text-xs font-bold rounded w-5 h-5 flex items-center justify-center shadow hover:bg-red-700 focus:outline-none delete-profile-photo-btn" data-photo-path="{{ $user->profile_photo }}" style="opacity:0.9; transform: translate(25%,-25%);">X</button>
                            </div>
                        @endif
                        <input type="file" name="profile_photo" class="file-input file-input-bordered w-full @error('profile_photo') file-input-error @enderror" accept="image/*">
                        @error('profile_photo')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>


                    <!-- GPS Location Tracker for Technicians -->
                    @if($user->role === 'technician')
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">GPS Location Sharing</h3>
                        <p class="text-sm text-base-content/70">
                            Share your real-time GPS location so administrators can see where you are on the map.
                        </p>
                        
                        <!-- Hidden fields to persist GPS location state -->
                        <input type="hidden" name="current_latitude" value="{{ $user->current_latitude }}">
                        <input type="hidden" name="current_longitude" value="{{ $user->current_longitude }}">
                        <input type="hidden" name="location_updated_at" value="{{ $user->location_updated_at }}">
                        <input type="hidden" name="location_sharing_enabled" value="{{ $user->location_sharing_enabled ? '1' : '0' }}">
                        
                        <x-gps-location-tracker :update-interval="60000" :show-status="true" :initial-sharing-enabled="$user->location_sharing_enabled" />
                    </div>
                    @endif
                </div>

                <!-- Right Column: Password and Notification Preferences -->
                <div class="space-y-8">
                    <!-- Password Section -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">Change Password</h3>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-2">Current Password</label>
                            <div class="relative">
                                <input type="password" name="current_password" class="input input-bordered w-full pr-12" autocomplete="current-password" id="current_password">
                                <button type="button" tabindex="-1" class="absolute right-0 top-0 h-full flex items-center px-3 text-base-content/60 hover:text-base-content focus:outline-none z-10" style="border:none; background:transparent;" onclick="togglePassword('current_password', this)">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </button>
                            </div>
                            @error('current_password')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-2">New Password</label>
                            <div class="relative">
                                <input type="password" name="password" class="input input-bordered w-full pr-12" autocomplete="new-password" id="new_password">
                                <button type="button" tabindex="-1" class="absolute right-0 top-0 h-full flex items-center px-3 text-base-content/60 hover:text-base-content focus:outline-none z-10" style="border:none; background:transparent;" onclick="togglePassword('new_password', this)">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </button>
                            </div>
                            @error('password')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-2">Confirm New Password</label>
                            <div class="relative">
                                <input type="password" name="password_confirmation" class="input input-bordered w-full pr-12" autocomplete="new-password" id="password_confirmation">
                                <button type="button" tabindex="-1" class="absolute right-0 top-0 h-full flex items-center px-3 text-base-content/60 hover:text-base-content focus:outline-none z-10" style="border:none; background:transparent;" onclick="togglePassword('password_confirmation', this)">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </button>
                            </div>
                            @error('password_confirmation')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                            @php
                                $minLength = \App\Models\Setting::getValue('password_min_length', 8);
                                $requireComplexity = \App\Models\Setting::getValue('require_password_complexity', 0);
                            @endphp
                            <div class="mt-3 rounded-lg bg-base-200/80 border border-base-300 px-4 py-3 flex items-start gap-3">
                                <svg class="w-5 h-5 mt-0.5 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div class="text-sm text-base-content/80">
                                    <span class="font-semibold">Password requirements:</span>
                                    <ul class="list-disc ml-5 mt-1 space-y-0.5">
                                        <li>At least <span class="font-semibold">{{ $minLength }}</span> characters</li>
                                        @if($requireComplexity)
                                            <li>Uppercase &amp; lowercase letters</li>
                                            <li>At least one number</li>
                                            <li>At least one special character</li>
                                        @endif
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notification Preferences -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">Notification Preferences</h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3">
                                <span class="text-base-content">Appointment Reminders</span>
                                <input type="checkbox" name="appointment_reminders" value="1" 
                                       class="checkbox checkbox-primary" {{ old('appointment_reminders', $user->appointment_reminders) ? 'checked' : '' }}>
                            </div>
                            <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3">
                                <span class="text-base-content">Mailing List</span>
                                <input type="checkbox" name="mailing_list" value="1" 
                                       class="checkbox checkbox-primary" {{ old('mailing_list', $user->mailing_list) ? 'checked' : '' }}>
                            </div>
                            <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3">
                                <span class="text-base-content">Monthly Billing</span>
                                <input type="checkbox" name="monthly_billing" value="1" 
                                       class="checkbox checkbox-primary" {{ old('monthly_billing', $user->monthly_billing) ? 'checked' : '' }}>
                            </div>
                            <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3">
                                <span class="text-base-content">Service Reports</span>
                                <div class="flex items-center space-x-4">
                                    <input type="checkbox" name="service_reports_enabled" id="service_reports_enabled" value="1" 
                                           class="checkbox checkbox-primary" {{ old('service_reports_enabled', $user->service_reports !== 'none') ? 'checked' : '' }}>
                                    <select name="service_reports" id="service_reports" class="select select-bordered w-32">
                                        <option value="full" {{ old('service_reports', $user->service_reports) == 'full' ? 'selected' : '' }}>Full Reports</option>
                                        <option value="invoice_only" {{ old('service_reports', $user->service_reports) == 'invoice_only' ? 'selected' : '' }}>Invoice Only</option>
                                        <option value="services_only" {{ old('service_reports', $user->service_reports) == 'services_only' ? 'selected' : '' }}>Services Only</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes Section (Client only) -->
            @if($user->role === 'client')
            <div class="mt-8 space-y-6">
                <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">Customer Notes</h3>
                <div>
                    <label class="block text-sm font-medium text-base-content mb-2">Notes</label>
                    <textarea name="notes_by_client" class="textarea textarea-bordered w-full">{{ old('notes_by_client', $user->notes_by_client) }}</textarea>
                </div>
            </div>
            @endif

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-4">
                <button type="submit" class="btn btn-primary">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Service reports checkbox behavior and profile photo deletion
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

    // Profile photo deletion
    document.querySelectorAll('.delete-profile-photo-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to delete your profile photo? This action cannot be undone.')) {
                return;
            }
            const photoPath = btn.getAttribute('data-photo-path');
            if (!photoPath) return alert('Could not determine photo path.');
            // Build AJAX request
            fetch('/profile/delete-photo', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ photo: photoPath })
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                }
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    // Remove the photo display and button
                    const photoContainer = btn.closest('.relative');
                    if (photoContainer) {
                        photoContainer.remove();
                    }
                    // Show success message
                    if (data.message) {
                        // You could replace this with a toast notification if you have one
                        console.log(data.message);
                    }
                } else {
                    throw new Error(data.error || 'Failed to delete photo.');
                }
            })
            .catch(error => {
                console.error('Photo deletion error:', error);
                alert('Failed to delete photo: ' + error.message);
            });
        });
    });
});

function togglePassword(id, btn) {
    const input = document.getElementById(id);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.querySelector('svg').classList.add('text-primary');
    } else {
        input.type = 'password';
        btn.querySelector('svg').classList.remove('text-primary');
    }
}

function logFormData(event) {
    const form = event.target;
    const formData = new FormData(form);
    
    console.log('=== FORM DATA BEING SUBMITTED ===');
    for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
    }
    console.log('=== END FORM DATA ===');
    
    // Don't prevent default - let the form submit normally
}
</script>
@endsection

