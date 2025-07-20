<form action="{{ route('admin.settings.general') }}" method="POST" enctype="multipart/form-data">
    @csrf
    
    <div class="space-y-8">
        <!-- Site Information -->
        <div>
            <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2 mb-4">
                Site Information
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="site_title" class="block text-sm font-medium text-base-content mb-2">
                        Site Title *
                    </label>
                    <input type="text" name="site_title" id="site_title" 
                           value="{{ \App\Models\Setting::getValue('site_title', 'RoutePilot Pro') }}"
                           class="input input-bordered w-full @error('site_title') input-error @enderror" 
                           required>
                    @error('site_title')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="site_tagline" class="block text-sm font-medium text-base-content mb-2">
                        Site Tagline
                    </label>
                    <input type="text" name="site_tagline" id="site_tagline" 
                           value="{{ \App\Models\Setting::getValue('site_tagline', 'Professional Pool Service Management') }}"
                           class="input input-bordered w-full @error('site_tagline') input-error @enderror">
                    @error('site_tagline')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Branding -->
        <div>
            <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2 mb-4">
                Branding
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="logo" class="block text-sm font-medium text-base-content mb-2">
                        Site Logo
                    </label>
                    <input type="file" name="logo" id="logo" 
                           accept="image/jpeg,image/png,image/jpg,image/gif,image/svg+xml"
                           class="file-input file-input-bordered w-full @error('logo') file-input-error @enderror">
                    @error('logo')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                    
                    @if(\App\Models\Setting::getValue('logo'))
                        <div class="mt-2">
                            <p class="text-sm text-base-content/70 mb-2">Current logo:</p>
                            <img src="{{ Storage::url(\App\Models\Setting::getValue('logo')) }}" 
                                 alt="Current Logo" class="h-12 object-contain">
                        </div>
                    @endif
                </div>
                
                <div>
                    <label for="favicon" class="block text-sm font-medium text-base-content mb-2">
                        Favicon
                    </label>
                    <input type="file" name="favicon" id="favicon" 
                           accept="image/x-icon,image/png,image/jpg"
                           class="file-input file-input-bordered w-full @error('favicon') file-input-error @enderror">
                    @error('favicon')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                    
                    @if(\App\Models\Setting::getValue('favicon'))
                        <div class="mt-2">
                            <p class="text-sm text-base-content/70 mb-2">Current favicon:</p>
                            <img src="{{ Storage::url(\App\Models\Setting::getValue('favicon')) }}" 
                                 alt="Current Favicon" class="h-8 w-8 object-contain">
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Background Image -->
        <div>
            <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2 mb-4">
                Background Image
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="background_image" class="block text-sm font-medium text-base-content mb-2">
                        Background Image
                    </label>
                    <input type="file" name="background_image" id="background_image" 
                           accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                           class="file-input file-input-bordered w-full @error('background_image') file-input-error @enderror">
                    @error('background_image')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-sm text-base-content/70 mt-1">
                        Upload a background image for the site. Recommended size: 1920x1080 or larger.
                    </p>
                    
                    @if(\App\Models\Setting::getValue('background_image'))
                        <div class="mt-2">
                            <p class="text-sm text-base-content/70 mb-2">Current background:</p>
                            <img src="{{ Storage::url(\App\Models\Setting::getValue('background_image')) }}" 
                                 alt="Current Background" class="h-24 w-full object-cover rounded">
                        </div>
                    @endif
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="label cursor-pointer">
                            <span class="label-text">Enable Background Image</span>
                            <input type="checkbox" name="background_enabled" value="1" 
                                   class="checkbox checkbox-primary"
                                   {{ \App\Models\Setting::getValue('background_enabled', '0') ? 'checked' : '' }}>
                        </label>
                        <p class="text-sm text-base-content/70 mt-2">
                            When enabled, the uploaded background image will be displayed on the site.
                        </p>
                    </div>
                    
                    <div>
                        <label class="label cursor-pointer">
                            <span class="label-text">Fixed Background (Parallax Effect)</span>
                            <input type="checkbox" name="background_fixed" value="1" 
                                   class="checkbox checkbox-primary"
                                   {{ \App\Models\Setting::getValue('background_fixed', '0') ? 'checked' : '' }}>
                        </label>
                        <p class="text-sm text-base-content/70 mt-2">
                            When enabled, the background image stays in place while the page scrolls (parallax effect). When disabled, the background scrolls with the page.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Company Information -->
        <div>
            <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2 mb-4">
                Company Information
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="company_name" class="block text-sm font-medium text-base-content mb-2">
                        Company Name
                    </label>
                    <input type="text" name="company_name" id="company_name" 
                           value="{{ \App\Models\Setting::getValue('company_name') }}"
                           class="input input-bordered w-full @error('company_name') input-error @enderror">
                    @error('company_name')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="company_email" class="block text-sm font-medium text-base-content mb-2">
                        Company Email
                    </label>
                    <input type="email" name="company_email" id="company_email" 
                           value="{{ \App\Models\Setting::getValue('company_email') }}"
                           class="input input-bordered w-full @error('company_email') input-error @enderror">
                    @error('company_email')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="company_phone" class="block text-sm font-medium text-base-content mb-2">
                        Company Phone
                    </label>
                    <input type="tel" name="company_phone" id="company_phone" 
                           value="{{ \App\Models\Setting::getValue('company_phone') }}"
                           class="input input-bordered w-full @error('company_phone') input-error @enderror">
                    @error('company_phone')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div class="md:col-span-2">
                    <label for="company_address" class="block text-sm font-medium text-base-content mb-2">
                        Company Address
                    </label>
                    <textarea name="company_address" id="company_address" rows="3"
                              class="textarea textarea-bordered w-full @error('company_address') textarea-error @enderror">{{ \App\Models\Setting::getValue('company_address') }}</textarea>
                    @error('company_address')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Save General Settings
            </button>
        </div>
    </div>
</form> 