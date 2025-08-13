<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\Setting;
use Carbon\Carbon;

class BackupService
{
    /**
     * Create a new backup.
     */
    public function createBackup()
    {
        try {
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $filename = "backup_{$timestamp}.sql";
            $backupPath = storage_path('app/backups/' . $filename);
            
            // Ensure backup directory exists with proper permissions
            $backupDir = storage_path('app/backups');
            if (!file_exists($backupDir)) {
                if (!mkdir($backupDir, 0755, true)) {
                    throw new \Exception('Failed to create backup directory');
                }
            }
            
            // Check if directory is writable
            if (!is_writable($backupDir)) {
                throw new \Exception('Backup directory is not writable');
            }
            
            // Get database configuration
            $host = config('database.connections.mysql.host');
            $database = config('database.connections.mysql.database');
            $username = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');
            
            // Validate database configuration
            if (!$host || !$database || !$username) {
                throw new \Exception('Database configuration is incomplete');
            }
            
            // Create backup command
            $command = "mysqldump --host={$host} --user={$username}";
            if ($password) {
                $command .= " --password={$password}";
            }
            $command .= " --single-transaction --routines --triggers {$database} > {$backupPath} 2>&1";
            
            // Execute backup
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                $errorOutput = implode("\n", $output);
                throw new \Exception("Database backup failed: {$errorOutput}");
            }
            
            // Verify backup file was created and has content
            if (!file_exists($backupPath) || filesize($backupPath) === 0) {
                throw new \Exception('Backup file was not created or is empty');
            }
            
            // Log backup creation
            Log::info("Database backup created successfully", [
                'filename' => $filename,
                'size' => $this->formatBytes(filesize($backupPath)),
                'path' => $backupPath
            ]);
            
            // Send notification if configured
            $this->sendBackupNotification($filename);
            
            // Clean up old backups
            $this->cleanupOldBackups();
            
            return $filename;
            
        } catch (\Exception $e) {
            Log::error("Database backup failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get backup information.
     */
    public function getBackupInfo()
    {
        $backupDir = storage_path('app/backups');
        $backups = [];
        
        if (file_exists($backupDir)) {
            $files = glob($backupDir . '/backup_*.sql');
            
            foreach ($files as $file) {
                $filename = basename($file);
                $size = filesize($file);
                $modified = filemtime($file);
                
                $backups[] = [
                    'filename' => $filename,
                    'size' => $this->formatBytes($size),
                    'size_bytes' => $size,
                    'created_at' => Carbon::createFromTimestamp($modified)->format('Y-m-d H:i:s'),
                    'created_at_timestamp' => $modified,
                ];
            }
            
            // Sort by creation date (newest first)
            usort($backups, function($a, $b) {
                return $b['created_at_timestamp'] - $a['created_at_timestamp'];
            });
        }
        
        return [
            'backups' => $backups,
            'total_backups' => count($backups),
            'total_size' => $this->formatBytes(array_sum(array_column($backups, 'size_bytes'))),
            'backup_enabled' => Setting::getValue('backup_enabled', '0'),
            'backup_frequency' => Setting::getValue('backup_frequency', 'daily'),
            'backup_retention_days' => Setting::getValue('backup_retention_days', '30'),
        ];
    }
    
    /**
     * Clean up old backups based on retention settings.
     */
    public function cleanupOldBackups()
    {
        $retentionDays = Setting::getValue('backup_retention_days', 30);
        $backupDir = storage_path('app/backups');
        
        if (!file_exists($backupDir)) {
            return;
        }
        
        $files = glob($backupDir . '/backup_*.sql');
        $cutoffTime = Carbon::now()->subDays($retentionDays)->timestamp;
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                Log::info("Deleted old backup: " . basename($file));
            }
        }
    }
    
    /**
     * Send backup notification email.
     */
    protected function sendBackupNotification($filename)
    {
        $notificationEmail = Setting::getValue('backup_notification_email');
        
        if (!$notificationEmail) {
            return;
        }
        
        try {
            Mail::raw("A new database backup has been created: {$filename}", function($message) use ($filename) {
                $message->to(Setting::getValue('backup_notification_email'))
                        ->subject('RoutePilot Pro - Database Backup Created')
                        ->line("A new database backup has been created: {$filename}");
            });
        } catch (\Exception $e) {
            Log::error('Failed to send backup notification: ' . $e->getMessage());
        }
    }
    
    /**
     * Format bytes to human readable format.
     */
    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Restore database from backup.
     */
    public function restoreBackup($filename)
    {
        $backupPath = storage_path('app/backups/' . $filename);
        
        if (!file_exists($backupPath)) {
            throw new \Exception('Backup file not found');
        }
        
        // Get database configuration
        $host = config('database.connections.mysql.host');
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        
        // Create restore command
        $command = "mysql --host={$host} --user={$username}";
        if ($password) {
            $command .= " --password={$password}";
        }
        $command .= " {$database} < {$backupPath}";
        
        // Execute restore
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \Exception('Database restore failed');
        }
        
        Log::info("Database restored from backup: {$filename}");
        
        return true;
    }
} 