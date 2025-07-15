@extends('layouts.app')

@section('title', 'Add Technician')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Add New Technician</h1>
            <p class="text-base-content/70 mt-2">Create a new pool service technician account</p>
        </div>
        <a href="{{ route('technicians.index') }}" class="btn btn-ghost">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Technicians
        </a>
    </div>

    <div class="card bg-base-100 shadow-xl border border-base-300">
        <div class="card-body">
            <form action="{{ route('technicians.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Personal Information -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-base-content">Personal Information</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">First Name *</span>
                                </label>
                                <input type="text" name="first_name" value="{{ old('first_name') }}" 
                                       class="input input-bordered @error('first_name') input-error @enderror" 
                                       placeholder="Enter first name" required>
                                @error('first_name')
                                    <label class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>
                            
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Last Name *</span>
                                </label>
                                <input type="text" name="last_name" value="{{ old('last_name') }}" 
                                       class="input input-bordered @error('last_name') input-error @enderror" 
                                       placeholder="Enter last name" required>
                                @error('last_name')
                                    <label class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Email Address *</span>
                            </label>
                            <input type="email" name="email" value="{{ old('email') }}" 
                                   class="input input-bordered @error('email') input-error @enderror" 
                                   placeholder="Enter email address" required>
                            @error('email')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Phone Number</span>
                            </label>
                            <input type="tel" name="phone" value="{{ old('phone') }}" 
                                   class="input input-bordered @error('phone') input-error @enderror" 
                                   placeholder="Enter phone number">
                            @error('phone')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Profile Photo</span>
                            </label>
                            <input type="file" name="profile_photo" 
                                   class="file-input file-input-bordered @error('profile_photo') file-input-error @enderror" 
                                   accept="image/*">
                            <label class="label">
                                <span class="label-text-alt">Max size: 2MB. Formats: JPEG, PNG, JPG, GIF</span>
                            </label>
                            @error('profile_photo')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>
                    </div>
                    
                    <!-- Address Information -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-base-content">Address Information</h3>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Street Address</span>
                            </label>
                            <input type="text" name="street_address" value="{{ old('street_address') }}" 
                                   class="input input-bordered @error('street_address') input-error @enderror" 
                                   placeholder="Enter street address">
                            @error('street_address')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Street Address 2</span>
                            </label>
                            <input type="text" name="street_address_2" value="{{ old('street_address_2') }}" 
                                   class="input input-bordered @error('street_address_2') input-error @enderror" 
                                   placeholder="Apartment, suite, etc. (optional)">
                            @error('street_address_2')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">City</span>
                                </label>
                                <input type="text" name="city" value="{{ old('city') }}" 
                                       class="input input-bordered @error('city') input-error @enderror" 
                                       placeholder="Enter city">
                                @error('city')
                                    <label class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>
                            
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">State</span>
                                </label>
                                <input type="text" name="state" value="{{ old('state') }}" 
                                       class="input input-bordered @error('state') input-error @enderror" 
                                       placeholder="Enter state">
                                @error('state')
                                    <label class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>
                            
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Zip Code</span>
                                </label>
                                <input type="text" name="zip_code" value="{{ old('zip_code') }}" 
                                       class="input input-bordered @error('zip_code') input-error @enderror" 
                                       placeholder="Enter zip code">
                                @error('zip_code')
                                    <label class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Account Information -->
                <div class="divider"></div>
                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-base-content">Account Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Password *</span>
                            </label>
                            <input type="password" name="password" 
                                   class="input input-bordered @error('password') input-error @enderror" 
                                   placeholder="Enter password" required>
                            @error('password')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Confirm Password *</span>
                            </label>
                            <input type="password" name="password_confirmation" 
                                   class="input input-bordered" 
                                   placeholder="Confirm password" required>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Status *</span>
                            </label>
                            <select name="status" class="select select-bordered @error('status') select-error @enderror" required>
                                <option value="">Select Status</option>
                                <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                <option value="pending" {{ old('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            </select>
                            @error('status')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Role *</span>
                            </label>
                            <select name="role" class="select select-bordered @error('role') select-error @enderror" required>
                                <option value="">Select Role</option>
                                <option value="tech" {{ old('role') == 'tech' ? 'selected' : '' }}>Technician</option>
                                <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                            </select>
                            @error('role')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="form-control">
                        <label class="label cursor-pointer">
                            <span class="label-text">Active Account</span>
                            <input type="checkbox" name="is_active" value="1" 
                                   class="checkbox checkbox-primary" {{ old('is_active', true) ? 'checked' : '' }}>
                        </label>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="divider"></div>
                <div class="flex justify-end space-x-4">
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
</div>
@endsection 