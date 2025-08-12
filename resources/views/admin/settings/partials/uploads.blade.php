<div class="space-y-6">
    <div>
        <h2 class="text-xl font-semibold text-base-content mb-4">File Upload Configuration</h2>
        <p class="text-base-content/70 mb-6">Configure file upload limits, allowed file types, and storage settings.</p>
    </div>

    <form action="{{ route('admin.settings.uploads') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        
        <!-- File Size Limits -->
        <div class="bg-base-200 rounded-lg p-4 mb-6">
            <h3 class="text-lg font-medium text-base-content mb-4">File Size Limits</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="max_file_size" class="block text-sm font-medium text-base-content mb-2">
                        Maximum File Size (MB)
                    </label>
                    <input type="number" 
                           id="max_file_size" 
                           name="max_file_size" 
                           value="{{ old('max_file_size', round(config('file-uploads.max_file_size', 25 * 1024 * 1024) / (1024 * 1024), 1)) }}"
                           min="1" 
                           max="100" 
                           step="0.1"
                           class="input input-bordered w-full" 
                           required>
                    <p class="text-xs text-base-content/60 mt-1">Maximum allowed file size in megabytes (1-100 MB)</p>
                </div>
                
                <div>
                    <label for="image_quality" class="block text-sm font-medium text-base-content mb-2">
                        Image Quality (%)
                    </label>
                    <input type="number" 
                           id="image_quality" 
                           name="image_quality" 
                           value="{{ old('image_quality', config('file-uploads.image_quality', 85)) }}"
                           min="1" 
                           max="100" 
                           class="input input-bordered w-full" 
                           required>
                    <p class="text-xs text-base-content/60 mt-1">JPEG quality for image compression (1-100%)</p>
                </div>
            </div>
        </div>

        <!-- Allowed File Types -->
        <div class="bg-base-200 rounded-lg p-4 mb-6">
            <h3 class="text-lg font-medium text-base-content mb-4">Allowed File Types</h3>
            
            <div class="space-y-3">
                @php
                    $allowedTypes = config('file-uploads.allowed_image_types', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);
                @endphp
                
                <div class="flex items-center">
                    <input type="checkbox" 
                           id="type_jpeg" 
                           name="allowed_types[]" 
                           value="image/jpeg"
                           {{ in_array('image/jpeg', $allowedTypes) ? 'checked' : '' }}
                           class="checkbox checkbox-primary">
                    <label for="type_jpeg" class="ml-3 text-sm text-base-content">JPEG (.jpg, .jpeg)</label>
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" 
                           id="type_png" 
                           name="allowed_types[]" 
                           value="image/png"
                           {{ in_array('image/png', $allowedTypes) ? 'checked' : '' }}
                           class="checkbox checkbox-primary">
                    <label for="type_png" class="ml-3 text-sm text-base-content">PNG (.png)</label>
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" 
                           id="type_gif" 
                           name="allowed_types[]" 
                           value="image/gif"
                           {{ in_array('image/gif', $allowedTypes) ? 'checked' : '' }}
                           class="checkbox checkbox-primary">
                    <label for="type_gif" class="ml-3 text-sm text-base-content">GIF (.gif)</label>
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" 
                           id="type_webp" 
                           name="allowed_types[]" 
                           value="image/webp"
                           {{ in_array('image/webp', $allowedTypes) ? 'checked' : '' }}
                           class="checkbox checkbox-primary">
                    <label for="type_webp" class="ml-3 text-sm text-base-content">WebP (.webp)</label>
                </div>
            </div>
            
            <p class="text-xs text-base-content/60 mt-3">Note: At least one file type must be selected.</p>
        </div>

        <!-- Storage Settings -->
        <div class="bg-base-200 rounded-lg p-4 mb-6">
            <h3 class="text-lg font-medium text-base-content mb-4">Storage Settings</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="storage_disk" class="block text-sm font-medium text-base-content mb-2">
                        Storage Disk
                    </label>
                    <select id="storage_disk" name="storage_disk" class="select select-bordered w-full">
                        <option value="public" {{ config('file-uploads.storage_disk', 'public') === 'public' ? 'selected' : '' }}>Public (Local)</option>
                        <option value="s3" {{ config('file-uploads.storage_disk') === 's3' ? 'selected' : '' }}>Amazon S3</option>
                        <option value="gcs" {{ config('file-uploads.storage_disk') === 'gcs' ? 'selected' : '' }}>Google Cloud Storage</option>
                    </select>
                    <p class="text-xs text-base-content/60 mt-1">Storage location for uploaded files</p>
                </div>
                
                <div>
                    <label for="generate_thumbnails" class="block text-sm font-medium text-base-content mb-2">
                        Generate Thumbnails
                    </label>
                    <select id="generate_thumbnails" name="generate_thumbnails" class="select select-bordered w-full">
                        <option value="1" {{ config('file-uploads.generate_thumbnails', true) ? 'selected' : '' }}>Yes</option>
                        <option value="0" {{ !config('file-uploads.generate_thumbnails', true) ? 'selected' : '' }}>No</option>
                    </select>
                    <p class="text-xs text-base-content/60 mt-1">Automatically create thumbnail versions of images</p>
                </div>
            </div>
        </div>

        <!-- Current Configuration Info -->
        <div class="bg-info/10 border border-info/20 rounded-lg p-4 mb-6">
            <h3 class="text-lg font-medium text-info mb-3">Current Configuration</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="font-medium">Max File Size:</span> 
                    <span class="text-info">{{ \App\Services\FileUploadService::getMaxFileSizeHuman() }}</span>
                </div>
                <div>
                    <span class="font-medium">Allowed Types:</span> 
                    <span class="text-info">{{ implode(', ', \App\Services\FileUploadService::getFileTypeExtensions()) }}</span>
                </div>
                <div>
                    <span class="font-medium">Storage Disk:</span> 
                    <span class="text-info">{{ config('file-uploads.storage_disk', 'public') }}</span>
                </div>
                <div>
                    <span class="font-medium">Thumbnails:</span> 
                    <span class="text-info">{{ config('file-uploads.generate_thumbnails', true) ? 'Enabled' : 'Disabled' }}</span>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Save Upload Settings
            </button>
        </div>
    </form>

    <!-- Help Information -->
    <div class="bg-warning/10 border border-warning/20 rounded-lg p-4">
        <h3 class="text-lg font-medium text-warning mb-3">💡 Tips & Best Practices</h3>
        <ul class="text-sm text-base-content/80 space-y-2">
            <li>• <strong>File Size:</strong> Larger limits may impact upload performance and storage usage</li>
            <li>• <strong>File Types:</strong> JPEG is recommended for photos, PNG for graphics with transparency</li>
            <li>• <strong>Storage:</strong> Public disk stores files locally, S3/GCS for cloud storage</li>
            <li>• <strong>Thumbnails:</strong> Enabling thumbnails improves page load performance</li>
            <li>• <strong>Security:</strong> Only image files are allowed to prevent security issues</li>
        </ul>
    </div>
</div>
