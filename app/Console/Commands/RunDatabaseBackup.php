<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BackupService;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RunDatabaseBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:run {--force : Force backup regardless of schedule}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run database backup based on configured schedule';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService)
    {
        $this->info('🔍 Checking backup configuration...');
        
        // Check if backups are enabled
        $backupEnabled = Setting::getValue('backup_enabled', '0');
        if (!$backupEnabled && !$this->option('force')) {
            $this->warn('⚠️  Automatic backups are disabled. Use --force to run anyway.');
            return 0;
        }
        
        // Check if it's time for a backup
        if (!$this->option('force') && !$this->shouldRunBackup()) {
            $this->info('✅ Not time for backup yet. Use --force to run anyway.');
            return 0;
        }
        
        $this->info('🚀 Starting database backup...');
        
        try {
            $filename = $backupService->createBackup();
            
            $this->info("✅ Backup completed successfully: {$filename}");
            
            // Log the successful backup
            Log::info('Automatic database backup completed', [
                'filename' => $filename,
                'command' => 'backup:run',
                'timestamp' => Carbon::now()->toISOString()
            ]);
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("❌ Backup failed: {$e->getMessage()}");
            
            // Log the failure
            Log::error('Automatic database backup failed', [
                'error' => $e->getMessage(),
                'command' => 'backup:run',
                'timestamp' => Carbon::now()->toISOString()
            ]);
            
            return 1;
        }
    }
    
    /**
     * Check if backup should run based on frequency and last backup time.
     */
    private function shouldRunBackup(): bool
    {
        $frequency = Setting::getValue('backup_frequency', 'daily');
        $lastBackupKey = "last_backup_{$frequency}";
        $lastBackupTime = Setting::getValue($lastBackupKey);
        
        if (!$lastBackupTime) {
            // First time running, should backup
            return true;
        }
        
        $lastBackup = Carbon::parse($lastBackupTime);
        $now = Carbon::now();
        
        switch ($frequency) {
            case 'daily':
                return $now->diffInDays($lastBackup) >= 1;
            case 'weekly':
                return $now->diffInWeeks($lastBackup) >= 1;
            case 'monthly':
                return $now->diffInMonths($lastBackup) >= 1;
            default:
                return false;
        }
    }
}
