<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Activity;
use Carbon\Carbon;
use App\Services\LoggingService;

class CleanupOldActivities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activities:cleanup {--days= : Number of days to retain (overrides config)} {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old activities based on retention settings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $retentionDays = $this->option('days') ?? config('app.activity_retention_days', 365);
        $isDryRun = $this->option('dry-run');
        
        $this->info("Activity Cleanup Process");
        $this->info("========================");
        $this->info("Retention Period: {$retentionDays} days");
        $this->info("Mode: " . ($isDryRun ? 'Dry Run (no changes will be made)' : 'Live Run'));
        $this->info("");
        
        // Count activities that would be deleted
        $cutoffDate = Carbon::now()->subDays($retentionDays);
        $activitiesToDelete = Activity::where('created_at', '<', $cutoffDate);
        $count = $activitiesToDelete->count();
        
        if ($count === 0) {
            $this->info("✅ No activities found older than {$retentionDays} days.");
            return 0;
        }
        
        $this->warn("Found {$count} activities older than {$retentionDays} days.");
        
        if ($isDryRun) {
            $this->info("This is a dry run. No activities will be deleted.");
            $this->info("To perform actual cleanup, run without --dry-run flag.");
            return 0;
        }
        
        // Confirm deletion
        if (!$this->confirm("Are you sure you want to delete {$count} activities?")) {
            $this->info("Cleanup cancelled.");
            return 0;
        }
        
        // Perform the cleanup
        $this->info("Deleting old activities...");
        
        try {
            $deletedCount = $activitiesToDelete->delete();
            
            $this->info("✅ Successfully deleted {$deletedCount} activities.");
            
            // Log the cleanup
            LoggingService::logUserAction('performed automatic activity cleanup', [
                'retention_days' => $retentionDays,
                'deleted_count' => $deletedCount,
                'cutoff_date' => $cutoffDate->toISOString(),
            ]);
            
            // Show remaining statistics
            $totalActivities = Activity::count();
            $this->info("Remaining activities: {$totalActivities}");
            
        } catch (\Exception $e) {
            $this->error("❌ Error during cleanup: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
