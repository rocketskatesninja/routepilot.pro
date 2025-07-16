@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Add New Technician</h1>
            <p class="text-base-content/70 mt-2">Create a new pool service technician account</p>
        </div>
        <a href="{{ route('technicians.index') }}" class="btn btn-outline">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Technicians
        </a>
    </div>

    <!-- Technician Form -->
    <div class="card bg-base-100 shadow-xl border border-base-300">
        <form action="{{ route('technicians.store') }}" method="POST" enctype="multipart/form-data" class="card-body p-6">
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

                    <div>
                        <label for="role" class="block text-sm font-medium text-base-content mb-2">
                            Role <span class="text-error">*</span>
                        </label>
                        <select name="role" id="role" class="select select-bordered w-full @error('role') select-error @enderror" required>
                            <option value="">Select Role</option>
                            <option value="tech" {{ old('role') == 'tech' ? 'selected' : '' }}>Technician</option>
                            <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                        </select>
                        @error('role')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="profile_photo" class="block text-sm font-medium text-base-content mb-2">
                            Profile Photo
                        </label>
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

            <!-- Account Information -->
            <div class="mt-8 space-y-6">
                <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2">
                    Account Information
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="space-y-4">
                        <div>
                            <label for="password" class="block text-sm font-medium text-base-content mb-2">
                                Password <span class="text-error">*</span>
                            </label>
                            <input type="password" name="password" id="password" 
                                   class="input input-bordered w-full @error('password') input-error @enderror" required>
                            @error('password')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-base-content mb-2">
                                Confirm Password <span class="text-error">*</span>
                            </label>
                            <input type="password" name="password_confirmation" id="password_confirmation" 
                                   class="input input-bordered w-full" required>
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
                        Admin Notes
                    </label>
                    <textarea name="notes_by_admin" id="notes_by_admin" rows="4" 
                              class="textarea textarea-bordered w-full @error('notes_by_admin') textarea-error @enderror"
                              placeholder="Any additional notes about this technician...">{{ old('notes_by_admin') }}</textarea>
                    @error('notes_by_admin')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-4">
                <a href="{{ route('technicians.index') }}" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Create Technician
                </button>
            </div>
        </form>
    </div>
</div>
@endsection 