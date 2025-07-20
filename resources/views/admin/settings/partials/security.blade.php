<form action="{{ route('admin.settings.security') }}" method="POST">
    @csrf
    
    <div class="space-y-8">
        <!-- Login Security -->
        <div>
            <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2 mb-4">
                Login Security
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="login_throttle_attempts" class="block text-sm font-medium text-base-content mb-2">
                        Login Throttle Attempts
                    </label>
                    <input type="number" name="login_throttle_attempts" id="login_throttle_attempts" 
                           value="{{ \App\Models\Setting::getValue('login_throttle_attempts', '5') }}"
                           min="1" max="10"
                           class="input input-bordered w-full @error('login_throttle_attempts') input-error @enderror" required>
                    @error('login_throttle_attempts')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-sm text-base-content/70 mt-1">
                        Number of failed login attempts before temporary lockout.
                    </p>
                </div>
                
                <div>
                    <label for="login_throttle_minutes" class="block text-sm font-medium text-base-content mb-2">
                        Login Throttle Minutes
                    </label>
                    <input type="number" name="login_throttle_minutes" id="login_throttle_minutes" 
                           value="{{ \App\Models\Setting::getValue('login_throttle_minutes', '15') }}"
                           min="1" max="60"
                           class="input input-bordered w-full @error('login_throttle_minutes') input-error @enderror" required>
                    @error('login_throttle_minutes')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-sm text-base-content/70 mt-1">
                        Duration of lockout after exceeding failed attempts.
                    </p>
                </div>
                
                <div>
                    <label for="password_min_length" class="block text-sm font-medium text-base-content mb-2">
                        Password Minimum Length
                    </label>
                    <input type="number" name="password_min_length" id="password_min_length" 
                           value="{{ \App\Models\Setting::getValue('password_min_length', '8') }}"
                           min="6" max="20"
                           class="input input-bordered w-full @error('password_min_length') input-error @enderror" required>
                    @error('password_min_length')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-sm text-base-content/70 mt-1">
                        Minimum number of characters required for passwords.
                    </p>
                </div>
                
                <div>
                    <label class="label cursor-pointer">
                        <span class="label-text">Require Password Complexity</span>
                        <input type="checkbox" name="require_password_complexity" value="1" 
                               class="checkbox checkbox-primary"
                               {{ \App\Models\Setting::getValue('require_password_complexity', '0') ? 'checked' : '' }}>
                    </label>
                    <p class="text-sm text-base-content/70 mt-2">
                        Require passwords to contain uppercase, lowercase, numbers, and special characters.
                    </p>
                </div>
            </div>
        </div>

        <!-- Session Security -->
        <div>
            <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2 mb-4">
                Session Security
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="session_lifetime" class="block text-sm font-medium text-base-content mb-2">
                        Session Lifetime (Minutes)
                    </label>
                    <input type="number" name="session_lifetime" id="session_lifetime" 
                           value="{{ \App\Models\Setting::getValue('session_lifetime', '120') }}"
                           min="15" max="1440"
                           class="input input-bordered w-full @error('session_lifetime') input-error @enderror" required>
                    @error('session_lifetime')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-sm text-base-content/70 mt-1">
                        How long user sessions remain active before requiring re-login.
                    </p>
                </div>
            </div>
        </div>

        <!-- File Upload Security -->
        <div>
            <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2 mb-4">
                File Upload Security
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="max_file_upload_size" class="block text-sm font-medium text-base-content mb-2">
                        Maximum File Upload Size (MB)
                    </label>
                    <input type="number" name="max_file_upload_size" id="max_file_upload_size" 
                           value="{{ \App\Models\Setting::getValue('max_file_upload_size', '10') }}"
                           min="1" max="100"
                           class="input input-bordered w-full @error('max_file_upload_size') input-error @enderror" required>
                    @error('max_file_upload_size')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-sm text-base-content/70 mt-1">
                        Maximum allowed file size for uploads (photos, documents, etc.).
                    </p>
                </div>
                
                <div class="md:col-span-2">
                    <label for="allowed_file_types" class="block text-sm font-medium text-base-content mb-2">
                        Allowed File Types
                    </label>
                    <input type="text" name="allowed_file_types" id="allowed_file_types" 
                           value="{{ \App\Models\Setting::getValue('allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt') }}"
                           class="input input-bordered w-full @error('allowed_file_types') input-error @enderror" required>
                    @error('allowed_file_types')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-sm text-base-content/70 mt-1">
                        Comma-separated list of allowed file extensions (e.g., jpg,png,pdf).
                    </p>
                </div>
            </div>
        </div>

        <!-- Security Recommendations -->
        <div>
            <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2 mb-4">
                Security Recommendations
            </h3>
            
            <div class="bg-base-200 rounded-lg p-6">
                <div class="space-y-4">
                    <div class="flex items-start space-x-3">
                        <svg class="w-6 h-6 text-info mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <h4 class="font-medium text-base-content">Strong Passwords</h4>
                            <p class="text-sm text-base-content/70">
                                Enable password complexity requirements to ensure users create strong passwords.
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-start space-x-3">
                        <svg class="w-6 h-6 text-info mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <h4 class="font-medium text-base-content">Login Throttling</h4>
                            <p class="text-sm text-base-content/70">
                                Configure login throttling to prevent brute force attacks on user accounts.
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-start space-x-3">
                        <svg class="w-6 h-6 text-info mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <h4 class="font-medium text-base-content">File Upload Security</h4>
                            <p class="text-sm text-base-content/70">
                                Limit file upload sizes and restrict allowed file types to prevent malicious uploads.
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-start space-x-3">
                        <svg class="w-6 h-6 text-info mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <h4 class="font-medium text-base-content">Session Management</h4>
                            <p class="text-sm text-base-content/70">
                                Set appropriate session timeouts to balance security with user convenience.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Save Security Settings
            </button>
        </div>
    </div>
</form> 