<div class="space-y-8">
    <!-- Backup Configuration -->
    <div>
        <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2 mb-4">
            Backup Configuration
        </h3>
        
        <form action="{{ route('admin.settings.database') }}" method="POST">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="label cursor-pointer">
                        <span class="label-text">Enable Automatic Backups</span>
                        <input type="checkbox" name="backup_enabled" value="1" 
                               class="checkbox checkbox-primary"
                               {{ \App\Models\Setting::getValue('backup_enabled', '0') ? 'checked' : '' }}>
                    </label>
                    <p class="text-sm text-base-content/70 mt-2">
                        Automatically create database backups based on the configured schedule.
                    </p>
                </div>
                
                <div>
                    <label for="backup_frequency" class="block text-sm font-medium text-base-content mb-2">
                        Backup Frequency
                    </label>
                    <select name="backup_frequency" id="backup_frequency" 
                            class="select select-bordered w-full @error('backup_frequency') select-error @enderror">
                        <option value="daily" {{ \App\Models\Setting::getValue('backup_frequency', 'daily') === 'daily' ? 'selected' : '' }}>
                            Daily
                        </option>
                        <option value="weekly" {{ \App\Models\Setting::getValue('backup_frequency', 'daily') === 'weekly' ? 'selected' : '' }}>
                            Weekly
                        </option>
                        <option value="monthly" {{ \App\Models\Setting::getValue('backup_frequency', 'daily') === 'monthly' ? 'selected' : '' }}>
                            Monthly
                        </option>
                    </select>
                    @error('backup_frequency')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="backup_retention_days" class="block text-sm font-medium text-base-content mb-2">
                        Retention Period (Days)
                    </label>
                    <input type="number" name="backup_retention_days" id="backup_retention_days" 
                           value="{{ \App\Models\Setting::getValue('backup_retention_days', '30') }}"
                           min="1" max="365"
                           class="input input-bordered w-full @error('backup_retention_days') input-error @enderror">
                    @error('backup_retention_days')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-sm text-base-content/70 mt-1">
                        How long to keep backup files before automatic deletion.
                    </p>
                </div>
                
                <div>
                    <label for="backup_notification_email" class="block text-sm font-medium text-base-content mb-2">
                        Notification Email
                    </label>
                    <input type="email" name="backup_notification_email" id="backup_notification_email" 
                           value="{{ \App\Models\Setting::getValue('backup_notification_email') }}"
                           class="input input-bordered w-full @error('backup_notification_email') input-error @enderror">
                    @error('backup_notification_email')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-sm text-base-content/70 mt-1">
                        Email address to notify when backups are created.
                    </p>
                </div>
            </div>
            
            <div class="mt-6">
                <button type="submit" class="btn btn-primary">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Save Database Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Manual Backup Management -->
    <div>
        <h3 class="text-lg font-semibold text-base-content border-b border-base-300 pb-2 mb-4">
            Manual Backup Management
        </h3>
        
        <div class="flex justify-between items-center mb-6">
            <div>
                <h4 class="text-md font-medium text-base-content">Create Manual Backup</h4>
                <p class="text-sm text-base-content/70">Create a backup of the database immediately</p>
            </div>
            <button type="button" id="create-backup-btn" onclick="createBackup()" class="btn btn-primary">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                </svg>
                Create Backup
            </button>
        </div>

        <!-- Backup Statistics -->
        <div class="stats shadow mb-6">
            <div class="stat">
                <div class="stat-figure text-primary">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                    </svg>
                </div>
                <div class="stat-title">Total Backups</div>
                <div class="stat-value text-primary">{{ $backupInfo['total_backups'] ?? 0 }}</div>
            </div>
            
            <div class="stat">
                <div class="stat-figure text-secondary">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <div class="stat-title">Total Size</div>
                <div class="stat-value text-secondary">{{ $backupInfo['total_size'] ?? '0 B' }}</div>
            </div>
            
            <div class="stat">
                <div class="stat-figure text-accent">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="stat-title">Auto Backup</div>
                <div class="stat-value text-accent">{{ $backupInfo['backup_enabled'] ? 'Enabled' : 'Disabled' }}</div>
                <div class="stat-desc">{{ ucfirst($backupInfo['backup_frequency'] ?? 'daily') }}</div>
            </div>
        </div>

        <!-- Backup Files List -->
        @if(isset($backupInfo['backups']) && count($backupInfo['backups']) > 0)
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Size</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($backupInfo['backups'] as $backup)
                            <tr>
                                <td>
                                    <div class="flex items-center space-x-3">
                                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                                        </svg>
                                        <div>
                                            <div class="font-bold">{{ $backup['filename'] }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $backup['size'] }}</td>
                                <td>{{ $backup['created_at'] }}</td>
                                <td>
                                    <div class="flex space-x-2">
                                        <a href="{{ route('admin.settings.backup.download', $backup['filename']) }}" 
                                           class="btn btn-sm btn-outline">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            Download
                                        </a>
                                        <form action="{{ route('admin.settings.backup.delete', $backup['filename']) }}" 
                                              method="POST" class="inline" 
                                              onsubmit="return confirm('Are you sure you want to delete this backup?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-error">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-8">
                <svg class="w-16 h-16 mx-auto text-base-content/30 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                </svg>
                <h3 class="text-lg font-medium text-base-content mb-2">No Backups Found</h3>
                <p class="text-base-content/70">Create your first backup to get started.</p>
            </div>
        @endif
    </div>
</div> 