<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Activity;
use App\Models\User;
use App\Models\Client;
use App\Models\Location;
use App\Models\Invoice;
use App\Models\Report;
use Carbon\Carbon;

class SeedSampleActivities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activities:seed {--count=50 : Number of sample activities to create}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create sample activities for testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = (int) $this->option('count');
        
        $this->info("Creating {$count} sample activities...");
        
        $users = User::all();
        $clients = Client::all();
        $locations = Location::all();
        $invoices = Invoice::all();
        $reports = Report::all();
        
        if ($users->isEmpty()) {
            $this->error("No users found. Please create users first.");
            return 1;
        }
        
        $actions = ['create', 'update', 'delete', 'login', 'logout', 'export', 'import'];
        $models = [
            'App\Models\Client' => $clients,
            'App\Models\Location' => $locations,
            'App\Models\Invoice' => $invoices,
            'App\Models\Report' => $reports,
            'App\Models\User' => $users,
        ];
        
        $descriptions = [
            'create' => [
                'Created new client account',
                'Added new location for client',
                'Generated new invoice',
                'Created service report',
                'Added new technician',
            ],
            'update' => [
                'Updated client contact information',
                'Modified location details',
                'Updated invoice status',
                'Edited service report',
                'Updated technician profile',
            ],
            'delete' => [
                'Deleted client account',
                'Removed location',
                'Cancelled invoice',
                'Deleted service report',
                'Removed technician',
            ],
            'login' => [
                'User logged in',
                'Admin logged in',
                'Technician logged in',
            ],
            'logout' => [
                'User logged out',
                'Admin logged out',
                'Technician logged out',
            ],
            'export' => [
                'Exported client data',
                'Exported invoice data',
                'Exported report data',
                'Exported activities',
            ],
            'import' => [
                'Imported client data',
                'Imported location data',
                'Imported invoice data',
            ],
        ];
        
        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();
        
        for ($i = 0; $i < $count; $i++) {
            $action = $actions[array_rand($actions)];
            $user = $users->random();
            $description = $descriptions[$action][array_rand($descriptions[$action])] ?? "Performed {$action} action";
            
            // Randomly decide if this activity should have a related model
            $hasModel = rand(1, 3) === 1; // 33% chance
            $modelType = null;
            $modelId = null;
            $subject = null;
            
            if ($hasModel && !empty($models)) {
                $modelClass = array_rand($models);
                $modelCollection = $models[$modelClass];
                
                if (!$modelCollection->isEmpty()) {
                    $modelType = $modelClass;
                    $subject = $modelCollection->random();
                    $modelId = $subject->id;
                }
            }
            
            // Create activity with random date within last 6 months
            $randomDays = rand(0, 180);
            $createdAt = Carbon::now()->subDays($randomDays);
            
            Activity::create([
                'user_id' => $user->id,
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'description' => $description,
                'properties' => [
                    'ip_address' => '192.168.1.' . rand(1, 254),
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'session_id' => 'session_' . rand(1000, 9999),
                ],
                'ip_address' => '192.168.1.' . rand(1, 254),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        $this->info("✅ Successfully created {$count} sample activities!");
        
        // Show some statistics
        $totalActivities = Activity::count();
        $this->info("Total activities in database: {$totalActivities}");
        
        return 0;
    }
}
