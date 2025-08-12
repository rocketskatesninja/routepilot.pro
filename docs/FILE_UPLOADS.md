# File Upload Configuration

RoutePilot Pro includes a professional, configurable file upload system that handles large files gracefully without requiring server-level PHP configuration changes.

## Features

- **Configurable File Size Limits**: Set upload limits from 1MB to 100MB through the admin interface
- **Multiple File Type Support**: JPEG, PNG, GIF, and WebP images
- **Flexible Storage**: Support for local storage, Amazon S3, and Google Cloud Storage
- **Automatic Thumbnails**: Generate thumbnail versions for better performance
- **Professional Error Handling**: Clear user feedback and comprehensive logging
- **Easy Configuration**: All settings managed through the web interface

## Configuration

### Through Admin Interface (Recommended)

1. Navigate to **Admin → Settings → File Uploads**
2. Configure the following settings:
   - **Maximum File Size**: Set the maximum allowed file size in MB (1-100)
   - **Image Quality**: Set JPEG compression quality (1-100%)
   - **Allowed File Types**: Select which image formats to accept
   - **Storage Disk**: Choose storage location (Public, S3, GCS)
   - **Generate Thumbnails**: Enable/disable automatic thumbnail generation

### Through Configuration File

You can also modify `config/file-uploads.php` directly:

```php
return [
    'max_file_size' => env('MAX_FILE_SIZE', 25 * 1024 * 1024), // 25MB in bytes
    'allowed_image_types' => [
        'image/jpeg',
        'image/jpg',
        'image/png', 
        'image/gif',
        'image/webp'
    ],
    'storage_disk' => env('FILESYSTEM_DISK', 'public'),
    'image_quality' => env('IMAGE_QUALITY', 85),
    'generate_thumbnails' => env('GENERATE_THUMBNAILS', true),
];
```

### Environment Variables

You can also set these values in your `.env` file:

```env
MAX_FILE_SIZE=26214400
IMAGE_QUALITY=85
GENERATE_THUMBNAILS=true
FILESYSTEM_DISK=public
```

## Usage

### In Controllers

```php
use App\Services\FileUploadService;

class YourController extends Controller
{
    public function store(Request $request)
    {
        $fileUploadService = new FileUploadService();
        
        if ($request->hasFile('photo')) {
            $result = $fileUploadService->uploadFile(
                $request->file('photo'),
                'profile-photos',
                $user->profile_photo // old file to replace
            );
            
            if ($result['success']) {
                // File uploaded successfully
                $user->profile_photo = $result['path'];
                $user->save();
            } else {
                // Handle upload error
                return back()->withErrors(['photo' => $result['error']]);
            }
        }
    }
}
```

### In Form Requests

The validation rules automatically use the configured limits:

```php
public function rules()
{
    return [
        'photo' => [
            'nullable',
            'image',
            'mimes:jpeg,png,jpg,gif',
            'max:' . (config('file-uploads.max_file_size', 25 * 1024 * 1024) / 1024)
        ],
    ];
}
```

## Storage Options

### Local Storage (Default)
- Files stored in `storage/app/public/`
- Accessible via `/storage/` URL
- Good for development and small deployments

### Amazon S3
- Configure in `config/filesystems.php`
- Set `FILESYSTEM_DISK=s3` in `.env`
- Requires AWS credentials and bucket setup

### Google Cloud Storage
- Configure in `config/filesystems.php`
- Set `FILESYSTEM_DISK=gcs` in `.env`
- Requires GCP credentials and bucket setup

## Best Practices

1. **File Size Limits**: Balance between user convenience and server performance
2. **Image Quality**: 85% is usually a good balance between quality and file size
3. **File Types**: JPEG for photos, PNG for graphics with transparency
4. **Thumbnails**: Enable for better page load performance
5. **Storage**: Use cloud storage for production deployments

## Troubleshooting

### Upload Fails with "File Too Large"
- Check the configured `max_file_size` in admin settings
- Verify the file is actually smaller than the limit
- Check server-level PHP limits if using shared hosting

### Images Don't Display
- Verify storage permissions: `chmod -R 755 storage/`
- Check if storage link exists: `php artisan storage:link`
- Verify file paths in database

### Performance Issues
- Consider enabling thumbnail generation
- Use cloud storage for large deployments
- Monitor disk space usage

## Security Features

- **File Type Validation**: Only allows image files
- **Size Limits**: Prevents abuse and server overload
- **Unique Filenames**: Prevents filename conflicts and security issues
- **Path Validation**: Prevents directory traversal attacks
- **Logging**: Comprehensive audit trail of all uploads

## Migration from Old System

If you're upgrading from a previous version:

1. The new system is backward compatible
2. Existing file paths will continue to work
3. New uploads will use the new service
4. You can gradually migrate existing upload logic

## Support

For issues or questions about the file upload system:

1. Check the admin settings configuration
2. Review the application logs
3. Verify file permissions and storage setup
4. Contact support with specific error messages
