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
        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="card-body p-6">
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
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Email Address</label>
                        <input type="email" name="email" value="{{ $user->email }}" class="input input-bordered w-full" readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Phone Number</label>
                        <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" class="input input-bordered w-full">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Profile Photo</label>
                        @if($user->profile_photo)
                            <div class="mb-2">
                                <img src="{{ Storage::url($user->profile_photo) }}" alt="Current profile photo" class="w-20 h-20 rounded-lg object-cover">
                            </div>
                        @endif
                        <input type="file" name="profile_photo" class="file-input file-input-bordered w-full" accept="image/*">
                    </div>
                </div>

                <!-- Right Column: Password and Notification Preferences -->
                <div class="space-y-8">
                    <!-- Password Section -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">Change Password</h3>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-2">Current Password</label>
                            <input type="password" name="current_password" class="input input-bordered w-full" autocomplete="current-password">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-2">New Password</label>
                            <input type="password" name="password" class="input input-bordered w-full" autocomplete="new-password">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-2">Confirm New Password</label>
                            <input type="password" name="password_confirmation" class="input input-bordered w-full" autocomplete="new-password">
                        </div>
                    </div>

                    <!-- Notification Preferences -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">Notification Preferences</h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3">
                                <span class="text-base-content">Monthly Billing</span>
                                <input type="checkbox" name="monthly_billing" class="checkbox checkbox-primary" {{ old('monthly_billing', $user->monthly_billing) ? 'checked' : '' }}>
                            </div>
                            <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3">
                                <span class="text-base-content">Service Reports</span>
                                <input type="checkbox" name="service_reports" class="checkbox checkbox-primary" {{ old('service_reports', $user->service_reports) ? 'checked' : '' }}>
                            </div>
                            <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3">
                                <span class="text-base-content">Mailing List</span>
                                <input type="checkbox" name="mailing_list" class="checkbox checkbox-primary" {{ old('mailing_list', $user->mailing_list) ? 'checked' : '' }}>
                            </div>
                            <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-3">
                                <span class="text-base-content">Service Reminders</span>
                                <input type="checkbox" name="service_reminders" class="checkbox checkbox-primary" {{ old('service_reminders', $user->service_reminders) ? 'checked' : '' }}>
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
@endsection

