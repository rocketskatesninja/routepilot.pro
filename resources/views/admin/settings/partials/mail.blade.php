<div class="space-y-8">
    <!-- Mail Configuration -->
    <div>
        <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2 mb-4">
            Mail Server Configuration
        </h3>
        
        <form action="{{ route('admin.settings.mail') }}" method="POST">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="mail_driver" class="block text-sm font-medium text-base-content mb-2">
                        Mail Driver *
                    </label>
                    <select name="mail_driver" id="mail_driver" 
                            class="select select-bordered w-full @error('mail_driver') select-error @enderror" required>
                        <option value="smtp" {{ \App\Models\Setting::getValue('mail_driver', 'smtp') === 'smtp' ? 'selected' : '' }}>
                            SMTP
                        </option>
                        <option value="mailgun" {{ \App\Models\Setting::getValue('mail_driver', 'smtp') === 'mailgun' ? 'selected' : '' }}>
                            Mailgun
                        </option>
                        <option value="ses" {{ \App\Models\Setting::getValue('mail_driver', 'smtp') === 'ses' ? 'selected' : '' }}>
                            Amazon SES
                        </option>
                        <option value="postmark" {{ \App\Models\Setting::getValue('mail_driver', 'smtp') === 'postmark' ? 'selected' : '' }}>
                            Postmark
                        </option>
                        <option value="log" {{ \App\Models\Setting::getValue('mail_driver', 'smtp') === 'log' ? 'selected' : '' }}>
                            Log (for testing)
                        </option>
                        <option value="array" {{ \App\Models\Setting::getValue('mail_driver', 'smtp') === 'array' ? 'selected' : '' }}>
                            Array (for testing)
                        </option>
                    </select>
                    @error('mail_driver')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="mail_host" class="block text-sm font-medium text-base-content mb-2">
                        Mail Host
                    </label>
                    <input type="text" name="mail_host" id="mail_host" 
                           value="{{ \App\Models\Setting::getValue('mail_host', 'smtp.mailtrap.io') }}"
                           class="input input-bordered w-full @error('mail_host') input-error @enderror">
                    @error('mail_host')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-sm text-base-content/70 mt-1">
                        Required for SMTP driver (e.g., smtp.gmail.com, smtp.mailgun.org)
                    </p>
                </div>
                
                <div>
                    <label for="mail_port" class="block text-sm font-medium text-base-content mb-2">
                        Mail Port
                    </label>
                    <input type="number" name="mail_port" id="mail_port" 
                           value="{{ \App\Models\Setting::getValue('mail_port', '2525') }}"
                           class="input input-bordered w-full @error('mail_port') input-error @enderror">
                    @error('mail_port')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-sm text-base-content/70 mt-1">
                        Common ports: 587 (TLS), 465 (SSL), 25 (unencrypted)
                    </p>
                </div>
                
                <div>
                    <label for="mail_encryption" class="block text-sm font-medium text-base-content mb-2">
                        Encryption
                    </label>
                    <select name="mail_encryption" id="mail_encryption" 
                            class="select select-bordered w-full @error('mail_encryption') select-error @enderror">
                        <option value="tls" {{ \App\Models\Setting::getValue('mail_encryption', 'tls') === 'tls' ? 'selected' : '' }}>
                            TLS
                        </option>
                        <option value="ssl" {{ \App\Models\Setting::getValue('mail_encryption', 'tls') === 'ssl' ? 'selected' : '' }}>
                            SSL
                        </option>
                        <option value="" {{ \App\Models\Setting::getValue('mail_encryption', 'tls') === '' ? 'selected' : '' }}>
                            None
                        </option>
                    </select>
                    @error('mail_encryption')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="mail_username" class="block text-sm font-medium text-base-content mb-2">
                        Username
                    </label>
                    <input type="text" name="mail_username" id="mail_username" 
                           value="{{ \App\Models\Setting::getValue('mail_username') }}"
                           class="input input-bordered w-full @error('mail_username') input-error @enderror">
                    @error('mail_username')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="mail_password" class="block text-sm font-medium text-base-content mb-2">
                        Password
                    </label>
                    <input type="password" name="mail_password" id="mail_password" 
                           value="{{ \App\Models\Setting::getValue('mail_password') }}"
                           class="input input-bordered w-full @error('mail_password') input-error @enderror">
                    @error('mail_password')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="mail_from_address" class="block text-sm font-medium text-base-content mb-2">
                        From Address *
                    </label>
                    <input type="email" name="mail_from_address" id="mail_from_address" 
                           value="{{ \App\Models\Setting::getValue('mail_from_address', 'noreply@routepilot.pro') }}"
                           class="input input-bordered w-full @error('mail_from_address') input-error @enderror" required>
                    @error('mail_from_address')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="mail_from_name" class="block text-sm font-medium text-base-content mb-2">
                        From Name *
                    </label>
                    <input type="text" name="mail_from_name" id="mail_from_name" 
                           value="{{ \App\Models\Setting::getValue('mail_from_name', 'RoutePilot Pro') }}"
                           class="input input-bordered w-full @error('mail_from_name') input-error @enderror" required>
                    @error('mail_from_name')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            
            <div class="mt-6">
                <button type="submit" class="btn btn-primary">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Save Mail Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Mail Testing -->
    <div>
        <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2 mb-4">
            Test Mail Configuration
        </h3>
        
        <div class="bg-base-200 rounded-lg p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label for="test_email" class="block text-sm font-medium text-base-content mb-2">
                        Test Email Address
                    </label>
                    <input type="email" id="test_email" 
                           placeholder="Enter email to send test to"
                           class="input input-bordered w-full">
                </div>
                
                <div>
                    <button type="button" id="test-mail-btn" onclick="testMail()" class="btn btn-primary w-full">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        Send Test Email
                    </button>
                </div>
                
                <div>
                    <button type="button" onclick="loadEmailLogs()" class="btn btn-outline w-full">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        View Email Logs
                    </button>
                </div>
            </div>
            
            <div class="mt-4">
                <p class="text-sm text-base-content/70">
                    <strong>Note:</strong> Make sure your mail settings are configured correctly before testing. 
                    If using Gmail, you may need to enable "Less secure app access" or use an App Password.
                </p>
            </div>
        </div>
    </div>

    <!-- Email Logs -->
    <div id="email-logs-section" class="hidden">
        <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2 mb-4">
            Email Logs
        </h3>
        
        <div id="email-logs-content" class="bg-base-200 rounded-lg p-4 max-h-96 overflow-y-auto">
            <div class="text-center">
                <span class="loading loading-spinner loading-lg"></span>
                <p class="mt-2">Loading email logs...</p>
            </div>
        </div>
    </div>
</div>

<script>
function loadEmailLogs() {
    const logsSection = document.getElementById('email-logs-section');
    const logsContent = document.getElementById('email-logs-content');
    
    logsSection.classList.remove('hidden');
    logsContent.innerHTML = `
        <div class="text-center">
            <span class="loading loading-spinner loading-lg"></span>
            <p class="mt-2">Loading email logs...</p>
        </div>
    `;
    
    fetch('{{ route("admin.settings.email-logs") }}')
        .then(response => response.json())
        .then(logs => {
            if (logs.length === 0) {
                logsContent.innerHTML = `
                    <div class="text-center py-8">
                        <svg class="w-16 h-16 mx-auto text-base-content/30 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <h3 class="text-lg font-medium text-base-content mb-2">No Email Logs Found</h3>
                        <p class="text-base-content/70">No email-related logs found in the system.</p>
                    </div>
                `;
            } else {
                let logsHtml = '<div class="space-y-2">';
                logs.forEach(log => {
                    logsHtml += `
                        <div class="bg-base-100 rounded p-3 text-sm font-mono">
                            <div class="text-base-content/70">${log}</div>
                        </div>
                    `;
                });
                logsHtml += '</div>';
                logsContent.innerHTML = logsHtml;
            }
        })
        .catch(error => {
            logsContent.innerHTML = `
                <div class="alert alert-error">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                    <span>Failed to load email logs: ${error.message}</span>
                </div>
            `;
        });
}
</script> 