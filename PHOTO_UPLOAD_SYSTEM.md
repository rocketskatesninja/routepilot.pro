# Photo Upload System Documentation

## Overview

This document describes the comprehensive photo upload system implemented across all entities in the RoutePilot application.

## Entities with Photo Support

### 1. **Clients**
- **Field**: `profile_photo` (single photo)
- **Storage Path**: `clients/photos/`
- **Usage**: Profile photo for client accounts
- **Display**: Avatar in client views and lists

### 2. **Technicians**
- **Field**: `profile_photo` (single photo)
- **Storage Path**: `profile-photos/`
- **Usage**: Profile photo for technician accounts
- **Display**: Avatar in technician views and lists

### 3. **Locations**
- **Field**: `photos` (multiple photos - JSON array)
- **Storage Path**: `locations/photos/`
- **Usage**: Location photos for property documentation
- **Display**: Slideshow in location view page

### 4. **Reports**
- **Field**: `photos` (multiple photos - JSON array)
- **Storage Path**: `reports/photos/`
- **Usage**: Service report photos documenting work performed
- **Display**: Grid layout in report view page

### 5. **User Profiles**
- **Field**: `profile_photo` (single photo)
- **Storage Path**: `profile-photos/`
- **Usage**: User account profile photo
- **Display**: Avatar in profile and navigation

## Photo Upload Service

### Location: `app/Services/PhotoUploadService.php`

This service provides standardized methods for photo handling:

```php
// Upload single photo
PhotoUploadService::uploadPhoto($file, 'directory');

// Upload multiple photos
PhotoUploadService::uploadPhotos($files, 'directory');

// Delete photo
PhotoUploadService::deletePhoto($path);

// Delete multiple photos
PhotoUploadService::deletePhotos($paths);

// Get photo URL
PhotoUploadService::getPhotoUrl($path);

// Get multiple photo URLs
PhotoUploadService::getPhotoUrls($paths);
```

## Validation Rules

All photo uploads use consistent validation:

```php
'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
'photos' => 'nullable|array'
'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
```

## File Storage

- **Disk**: `public`
- **Max File Size**: 2MB per photo
- **Allowed Formats**: JPEG, PNG, JPG, GIF
- **Storage Structure**: Organized by entity type

## Photo Display

### Single Photos (Profile Photos)
```php
@if($entity->profile_photo)
    <img src="{{ Storage::url($entity->profile_photo) }}" alt="Profile Photo">
@else
    <!-- Fallback avatar with initials -->
@endif
```

### Multiple Photos (Location/Report Photos)
```php
@if($entity->photos && count($entity->photos) > 0)
    @foreach($entity->photos as $photo)
        <img src="{{ Storage::url($photo) }}" alt="Photo">
    @endforeach
@endif
```

## Photo Cleanup

### Automatic Deletion
When entities are deleted, associated photos are automatically removed:

- **Clients**: Profile photo deleted in `ClientController::destroy()`
- **Technicians**: Profile photo deleted in `TechnicianController::destroy()`
- **Locations**: All photos deleted in `LocationController::destroy()`
- **Reports**: All photos deleted in `ReportController::destroy()`

### Update Handling
When updating entities with new photos:

1. Old photos are deleted from storage
2. New photos are uploaded
3. Database is updated with new photo paths

## Form Implementation

### Single Photo Upload
```html
<input type="file" name="profile_photo" 
       class="file-input file-input-bordered w-full" 
       accept="image/*">
```

### Multiple Photo Upload
```html
<input type="file" name="photos[]" 
       class="file-input file-input-bordered w-full" 
       accept="image/*" multiple>
```

## Controller Implementation

### Example: Upload Single Photo
```php
if ($request->hasFile('profile_photo')) {
    // Delete old photo if exists
    if ($entity->profile_photo) {
        Storage::disk('public')->delete($entity->profile_photo);
    }
    $path = $request->file('profile_photo')->store('directory', 'public');
    $validated['profile_photo'] = $path;
}
```

### Example: Upload Multiple Photos
```php
if ($request->hasFile('photos')) {
    // Delete old photos if exist
    if ($entity->photos) {
        foreach ($entity->photos as $oldPhoto) {
            Storage::disk('public')->delete($oldPhoto);
        }
    }
    $photoPaths = [];
    foreach ($request->file('photos') as $photo) {
        $path = $photo->store('directory', 'public');
        $photoPaths[] = $path;
    }
    $validated['photos'] = $photoPaths;
}
```

## Security Considerations

1. **File Type Validation**: Only image files are allowed
2. **File Size Limits**: 2MB maximum per file
3. **Storage Isolation**: Photos stored in public disk with proper permissions
4. **Path Validation**: All file paths are validated before storage operations

## Performance Considerations

1. **Lazy Loading**: Photos are loaded only when needed
2. **Optimized Storage**: Photos are stored efficiently with unique names
3. **Cleanup**: Automatic deletion prevents storage bloat
4. **Caching**: Consider implementing image caching for frequently accessed photos

## Troubleshooting

### Common Issues

1. **Photos not displaying**: Check if `php artisan storage:link` has been run
2. **Upload failures**: Verify file permissions on storage directory
3. **Missing photos after update**: Ensure old photos are properly deleted before uploading new ones
4. **Validation errors**: Check file size and format requirements

### Debugging

```php
// Check if photo exists
if (Storage::disk('public')->exists($photoPath)) {
    // Photo exists
}

// Get photo URL
$url = Storage::url($photoPath);

// Delete photo
Storage::disk('public')->delete($photoPath);
```

## Future Enhancements

1. **Image Resizing**: Automatic thumbnail generation
2. **Watermarking**: Add watermarks to uploaded photos
3. **Compression**: Optimize photo file sizes
4. **CDN Integration**: Use CDN for faster photo delivery
5. **Photo Galleries**: Enhanced photo viewing with lightbox
6. **Bulk Operations**: Upload/delete multiple photos at once 