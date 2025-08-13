<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\PhotoUploadService;
use App\Services\BackupService;

class SettingsController extends Controller
{
    protected $photoUploadService;
    protected $backupService;

    public function __construct(PhotoUploadService $photoUploadService, BackupService $backupService)
    {
        $this->photoUploadService = $photoUploadService;
        $this->backupService = $backupService;
    }

    /**
     * Display the settings page with tabs.
     */
    public function index()
    {
        // Check if user is admin
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        
        $activeTab = request('tab', 'general');
        
        $settings = [
            'general' => Setting::getByGroup('general'),
            'database' => Setting::getByGroup('database'),
            'mail' => Setting::getByGroup('mail'),
            'security' => Setting::getByGroup('security'),
        ];

        // Get backup information
        $backupInfo = $this->backupService->getBackupInfo();
        
        return view('admin.settings.index', compact('settings', 'activeTab', 'backupInfo'));
    }

    /**
     * Update general settings.
     */
    public function updateGeneral(Request $request)
    {
        // Check if user is admin
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        $request->validate([
            'site_title' => 'required|string|max:255',
            'site_tagline' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:' . (25 * 1024),
            'favicon' => 'nullable|image|mimes:ico,png,jpg|max:' . (25 * 1024),
            'background_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:' . (25 * 1024),
            'background_enabled' => 'boolean',
            'background_fixed' => 'boolean',
            'company_name' => 'nullable|string|max:255',
            'company_address' => 'nullable|string|max:500',
            'company_phone' => 'nullable|string|max:20',
            'company_email' => 'nullable|email|max:255',
        ]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logoPath = $this->photoUploadService->handleSinglePhotoUploadWithField(
                $request, 
                'logo',
                'settings/logos',
                Setting::getValue('logo')
            );
            Setting::setValue('logo', $logoPath, 'string', 'general', 'Site logo image');
        }

        // Handle favicon upload
        if ($request->hasFile('favicon')) {
            $faviconPath = $this->photoUploadService->handleSinglePhotoUploadWithField(
                $request, 
                'favicon',
                'settings/favicons',
                Setting::getValue('favicon')
            );
            Setting::setValue('favicon', $faviconPath, 'string', 'general', 'Site favicon');
        }

        // Handle background image upload
        if ($request->hasFile('background_image')) {
            $backgroundPath = $this->photoUploadService->handleSinglePhotoUploadWithField(
                $request, 
                'background_image',
                'settings/backgrounds',
                Setting::getValue('background_image')
            );
            Setting::setValue('background_image', $backgroundPath, 'string', 'general', 'Site background image');
        }

        // Update text settings
        Setting::setValue('site_title', $request->site_title, 'string', 'general', 'Site title');
        Setting::setValue('site_tagline', $request->site_tagline, 'string', 'general', 'Site tagline');
        Setting::setValue('company_name', $request->company_name, 'string', 'general', 'Company name');
        Setting::setValue('company_address', $request->company_address, 'string', 'general', 'Company address');
        Setting::setValue('company_phone', $request->company_phone, 'string', 'general', 'Company phone');
        Setting::setValue('company_email', $request->company_email, 'string', 'general', 'Company email');
        Setting::setValue('background_enabled', $request->background_enabled ? '1' : '0', 'boolean', 'general', 'Enable background image');
        Setting::setValue('background_fixed', $request->background_fixed ? '1' : '0', 'boolean', 'general', 'Background image fixed');

        return redirect()->route('admin.settings.index', ['tab' => 'general'])
                        ->with('success', 'General settings updated successfully.');
    }

    /**
     * Update database settings.
     */
    public function updateDatabase(Request $request)
    {
        // Check if user is admin
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        
        $request->validate([
            'db_host' => 'required|string|max:255',
            'db_port' => 'required|integer|min:1|max:65535',
            'db_database' => 'required|string|max:255',
            'db_username' => 'required|string|max:255',
            'db_password' => 'nullable|string|max:255',
            'db_charset' => 'required|in:utf8mb4,utf8,latin1',
            'backup_enabled' => 'boolean',
            'backup_frequency' => 'required_if:backup_enabled,1|in:daily,weekly,monthly',
            'backup_retention_days' => 'required_if:backup_enabled,1|integer|min:1|max:365',
            'backup_notification_email' => 'nullable|email',
        ]);

        try {
            // Update database configuration
            $this->updateDatabaseConfig($request);
            
            // Update backup settings
            Setting::setValue('backup_enabled', $request->backup_enabled ? '1' : '0', 'boolean', 'database', 'Enable automatic backups');
            Setting::setValue('backup_frequency', $request->backup_frequency, 'string', 'database', 'Backup frequency');
            Setting::setValue('backup_retention_days', $request->backup_retention_days, 'integer', 'database', 'Backup retention days');
            Setting::setValue('backup_notification_email', $request->backup_notification_email, 'string', 'database', 'Backup notification email');

            return redirect()->route('admin.settings.index', ['tab' => 'database'])
                            ->with('success', 'Database settings updated successfully. You may need to restart your application for changes to take effect.');
                            
        } catch (\Exception $e) {
            Log::error('Failed to update database settings: ' . $e->getMessage());
            return redirect()->route('admin.settings.index', ['tab' => 'database'])
                            ->with('error', 'Failed to update database settings: ' . $e->getMessage());
        }
    }

    /**
     * Update database configuration file.
     */
    private function updateDatabaseConfig(Request $request)
    {
        $configPath = config_path('database.php');
        
        if (!file_exists($configPath)) {
            throw new \Exception('Database configuration file not found');
        }
        
        // Read current config
        $config = require $configPath;
        
        // Update MySQL connection settings
        $config['connections']['mysql']['host'] = $request->db_host;
        $config['connections']['mysql']['port'] = $request->db_port;
        $config['connections']['mysql']['database'] = $request->db_database;
        $config['connections']['mysql']['username'] = $request->db_username;
        $config['connections']['mysql']['charset'] = $request->db_charset;
        
        // Only update password if provided
        if ($request->filled('db_password')) {
            $config['connections']['mysql']['password'] = $request->db_password;
        }
        
        // Convert config array to PHP code
        $configContent = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        
        // Write updated config
        if (file_put_contents($configPath, $configContent) === false) {
            throw new \Exception('Failed to write database configuration file');
        }
        
        // Clear config cache
        \Artisan::call('config:clear');
        
        Log::info('Database configuration updated', [
            'host' => $request->db_host,
            'port' => $request->db_port,
            'database' => $request->db_database,
            'username' => $request->db_username,
            'charset' => $request->db_charset,
            'password_updated' => $request->filled('db_password')
        ]);
    }

    /**
     * Test database connection with provided credentials.
     */
    public function testDatabaseConnection(Request $request)
    {
        // Check if user is admin
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        
        $request->validate([
            'db_host' => 'required|string|max:255',
            'db_port' => 'required|integer|min:1|max:65535',
            'db_database' => 'required|string|max:255',
            'db_username' => 'required|string|max:255',
            'db_password' => 'nullable|string|max:255',
            'db_charset' => 'required|in:utf8mb4,utf8,latin1',
        ]);

        try {
            // Build temporary database configuration
            $tempConfig = [
                'host' => $request->db_host,
                'port' => $request->db_port,
                'database' => $request->db_database,
                'username' => $request->db_username,
                'password' => $request->db_password,
                'charset' => $request->db_charset,
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ];

            // Test connection
            $connection = new \Illuminate\Database\Connectors\MySqlConnector();
            $pdo = $connection->connect($tempConfig);
            
            // Test query
            $pdo->query("SELECT 1");
            
            // Get database info
            $version = $pdo->query("SELECT VERSION() as version")->fetch();
            $tables = $pdo->query("SHOW TABLES")->fetchAll();
            
            return response()->json([
                'success' => true,
                'message' => 'Database connection successful!',
                'details' => [
                    'version' => $version['version'] ?? 'Unknown',
                    'tables_count' => count($tables),
                    'host' => $request->db_host,
                    'port' => $request->db_port,
                    'database' => $request->db_database,
                    'charset' => $request->db_charset
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Database connection test failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Database connection failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset the cron job for automatic backups.
     */
    public function resetCronJob(Request $request)
    {
        // Check if user is admin
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        try {
            // Clear the last backup time settings to force immediate backup
            $frequencies = ['daily', 'weekly', 'monthly'];
            foreach ($frequencies as $frequency) {
                $lastBackupKey = "last_backup_{$frequency}";
                Setting::where('key', $lastBackupKey)->delete();
            }

            // Clear config cache to ensure new settings are loaded
            \Artisan::call('config:clear');
            
            // Create an immediate backup
            $backupFilename = $this->backupService->createBackup();
            
            Log::info('Cron job reset for automatic backups and immediate backup created', [
                'user_id' => auth()->id(),
                'timestamp' => now()->toISOString(),
                'backup_filename' => $backupFilename
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cron job reset successfully and immediate backup created: ' . $backupFilename
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to reset cron job: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset cron job: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update mail settings.
     */
    public function updateMail(Request $request)
    {
        // Check if user is admin
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        $request->validate([
            'mail_driver' => 'required|in:smtp,mailgun,ses,postmark,log,array',
            'mail_host' => 'required_if:mail_driver,smtp|string',
            'mail_port' => 'required_if:mail_driver,smtp|integer',
            'mail_username' => 'nullable|string',
            'mail_password' => 'nullable|string',
            'mail_encryption' => 'required_if:mail_driver,smtp|in:tls,ssl',
            'mail_from_address' => 'required|email',
            'mail_from_name' => 'required|string|max:255',
        ]);

        Setting::setValue('mail_driver', $request->mail_driver, 'string', 'mail', 'Mail driver');
        Setting::setValue('mail_host', $request->mail_host, 'string', 'mail', 'Mail host');
        Setting::setValue('mail_port', $request->mail_port, 'integer', 'mail', 'Mail port');
        Setting::setValue('mail_username', $request->mail_username, 'string', 'mail', 'Mail username');
        Setting::setValue('mail_password', $request->mail_password, 'string', 'mail', 'Mail password');
        Setting::setValue('mail_encryption', $request->mail_encryption, 'string', 'mail', 'Mail encryption');
        Setting::setValue('mail_from_address', $request->mail_from_address, 'string', 'mail', 'Mail from address');
        Setting::setValue('mail_from_name', $request->mail_from_name, 'string', 'mail', 'Mail from name');

        return redirect()->route('admin.settings.index', ['tab' => 'mail'])
                        ->with('success', 'Mail settings updated successfully.');
    }

    /**
     * Update security settings.
     */
    public function updateSecurity(Request $request)
    {
        // Check if user is admin
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        $request->validate([
            'login_throttle_attempts' => 'required|integer|min:1|max:10',
            'login_throttle_minutes' => 'required|integer|min:1|max:60',
            'max_file_upload_size' => 'required|integer|min:1|max:100',
            'allowed_file_types' => 'required|string',
            'session_lifetime' => 'required|integer|min:15|max:1440',
            'password_min_length' => 'required|integer|min:6|max:20',
            'require_password_complexity' => 'boolean',
        ]);

        Setting::setValue('login_throttle_attempts', $request->login_throttle_attempts, 'integer', 'security', 'Login throttle attempts');
        Setting::setValue('login_throttle_minutes', $request->login_throttle_minutes, 'integer', 'security', 'Login throttle minutes');
        Setting::setValue('max_file_upload_size', $request->max_file_upload_size, 'integer', 'security', 'Max file upload size (MB)');
        Setting::setValue('allowed_file_types', $request->allowed_file_types, 'string', 'security', 'Allowed file types');
        Setting::setValue('session_lifetime', $request->session_lifetime, 'integer', 'security', 'Session lifetime (minutes)');
        Setting::setValue('password_min_length', $request->password_min_length, 'integer', 'security', 'Password minimum length');
        Setting::setValue('require_password_complexity', $request->require_password_complexity ? '1' : '0', 'boolean', 'security', 'Require password complexity');

        return redirect()->route('admin.settings.index', ['tab' => 'security'])
                        ->with('success', 'Security settings updated successfully.');
    }

    /**
     * Create a manual backup.
     */
    public function createBackup()
    {
        // Check if user is admin
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        try {
            $backupPath = $this->backupService->createBackup();
            
            return response()->json([
                'success' => true,
                'message' => 'Backup created successfully',
                'path' => $backupPath
            ]);
        } catch (\Exception $e) {
            Log::error('Backup creation failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Backup creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download a backup file.
     */
    public function downloadBackup($filename)
    {
        // Check if user is admin
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        $backupPath = storage_path('app/backups/' . $filename);
        
        if (!file_exists($backupPath)) {
            return back()->with('error', 'Backup file not found.');
        }

        return response()->download($backupPath);
    }

    /**
     * Delete a backup file.
     */
    public function deleteBackup($filename)
    {
        // Check if user is admin
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        $backupPath = storage_path('app/backups/' . $filename);
        
        if (file_exists($backupPath)) {
            unlink($backupPath);
            return back()->with('success', 'Backup deleted successfully.');
        }

        return back()->with('error', 'Backup file not found.');
    }

    /**
     * Test mail configuration.
     */
    public function testMail(Request $request)
    {
        // Check if user is admin
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        $request->validate([
            'test_email' => 'required|email'
        ]);

        try {
            // Send test email
            \Mail::raw('This is a test email from RoutePilot Pro to verify your mail configuration.', function($message) use ($request) {
                $message->to($request->test_email)
                        ->subject('RoutePilot Pro - Mail Configuration Test');
            });

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully!'
            ]);
        } catch (\Exception $e) {
            Log::error('Mail test failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Mail test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get email logs.
     */
    public function getEmailLogs()
    {
        // Check if user is admin
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }
        $logs = [];
        $logFile = storage_path('logs/laravel.log');
        
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            $lines = explode("\n", $logContent);
            
            // Filter for email-related logs
            $emailLogs = array_filter($lines, function($line) {
                return strpos($line, 'mail') !== false || strpos($line, 'email') !== false;
            });
            
            $logs = array_slice(array_reverse($emailLogs), 0, 100); // Last 100 email logs
        }

        return response()->json($logs);
    }


}
